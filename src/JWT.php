<?php

declare(strict_types=1);

namespace Daycry\JWT;

use DateInterval;
use DateTimeImmutable;
use Daycry\JWT\Config\JWT as JWTConfig;
use Daycry\JWT\Enums\AlgorithmType;
use Daycry\JWT\Enums\ConstraintName;
use Daycry\JWT\Exceptions\InvalidTokenException;
use Daycry\JWT\Exceptions\JWTConfigurationException;
use InvalidArgumentException;
use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer;
use Lcobucci\JWT\Signer\Hmac;
use Lcobucci\JWT\Signer\Key;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\OpenSSL;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Token\RegisteredClaimGiven;
use Lcobucci\JWT\Validation\Constraint;
use Lcobucci\JWT\Validation\Constraint\IdentifiedBy;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\LooseValidAt;
use Lcobucci\JWT\Validation\Constraint\PermittedFor;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Constraint\StrictValidAt;
use Lcobucci\JWT\Validation\RequiredConstraintsViolated;
use Throwable;

/**
 * Immutable JWT facade over `lcobucci/jwt ^5`.
 *
 * Construct via the constructor (with an explicit config) or `JWT::for()` and
 * configure per-call needs through the `with*()` methods, which return a new
 * instance — the original is unchanged.
 */
final class JWT
{
    /**
     * Claim names the facade manages itself. They cannot be reused as the
     * compact-payload claim name (`withParamData()`); `uid` plus the registered
     * JWT claims would otherwise be silently shadowed or rejected by lcobucci.
     *
     * @var list<string>
     */
    private const RESERVED_CLAIMS = ['uid', 'aud', 'exp', 'jti', 'iat', 'iss', 'nbf', 'sub'];

    private bool $split = false;

    /**
     * @var non-empty-string
     */
    private string $paramData = 'data';

    private ?int $leewaySeconds;
    private ?string $expiresAtOverride = null;

    /**
     * Lazily-built, memoized signer + key configuration. Stateless (no clock),
     * so it is safe to reuse across calls and to share across `with*()` clones.
     */
    private ?Configuration $configuration = null;

    public function __construct(private JWTConfig $config)
    {
        $this->leewaySeconds = $config->leeway;
    }

    /**
     * Static factory. Falls back to the bound `Config\JWT` when no instance is provided.
     */
    public static function for(?JWTConfig $config = null): self
    {
        return new self($config ?? config('JWT'));
    }

    public function withSplitData(bool $split = true): self
    {
        $clone        = clone $this;
        $clone->split = $split;

        return $clone;
    }

    public function withParamData(string $name): self
    {
        if ($name === '') {
            throw new InvalidArgumentException('paramData claim name cannot be empty.');
        }
        if (in_array($name, self::RESERVED_CLAIMS, true)) {
            throw new InvalidArgumentException(sprintf(
                'paramData claim name "%s" is reserved by the library; choose another name.',
                $name,
            ));
        }
        $clone            = clone $this;
        $clone->paramData = $name;

        return $clone;
    }

    public function withLeeway(?int $seconds): self
    {
        if ($seconds !== null && $seconds < 0) {
            throw new InvalidArgumentException('Leeway cannot be negative.');
        }
        $clone                = clone $this;
        $clone->leewaySeconds = $seconds;

        return $clone;
    }

    /**
     * Override the configured `expiresAt` modifier for this instance only.
     *
     * Enables short-lived access tokens without mutating the shared config:
     * `JWT::for()->withExpiresAt('+5 minutes')->encode($data)`.
     */
    public function withExpiresAt(string $modifier): self
    {
        if ($modifier === '') {
            throw new InvalidArgumentException('expiresAt modifier cannot be empty.');
        }
        $clone                    = clone $this;
        $clone->expiresAtOverride = $modifier;

        return $clone;
    }

    public function getParamData(): string
    {
        return $this->paramData;
    }

    public function isSplitData(): bool
    {
        return $this->split;
    }

    public function encode(mixed $data, int|string|null $uid = null): string
    {
        $now           = new DateTimeImmutable();
        $configuration = $this->buildConfiguration();

        $issuer     = $this->requireClaim($this->config->issuer, 'issuer');
        $audience   = $this->requireClaim($this->config->audience, 'audience');
        $identifier = $this->requireClaim($this->config->identifier, 'identifier');

        $builder = $this->applyPayload($configuration->builder(), $data);

        $resolvedUid = $uid ?? $this->config->uid;
        if ($resolvedUid !== null && $resolvedUid !== '') {
            if ($this->split && $this->dataHasUidKey($data)) {
                // The framework-owned uid claim overwrites a same-named split key.
                $this->logUidCollisionWarning();
            }
            $builder = $builder->withClaim('uid', $resolvedUid);
        }

        $notBefore = $this->applyModifier($now, $this->config->canOnlyBeUsedAfter, 'canOnlyBeUsedAfter');
        if ($notBefore > $now) {
            // Documented behaviour: clamp a future "not before" back to issuance time
            // so freshly-minted tokens are immediately usable.
            $notBefore = $now;
        }

        $expiresAt = $this->applyModifier(
            $now,
            $this->expiresAtOverride ?? $this->config->expiresAt,
            'expiresAt',
        );

        $token = $builder
            ->issuedBy($issuer)
            ->permittedFor($audience)
            ->identifiedBy($identifier)
            ->issuedAt($now)
            ->canOnlyBeUsedAfter($notBefore)
            ->expiresAt($expiresAt)
            ->getToken($configuration->signer(), $configuration->signingKey());

        return $token->toString();
    }

    /**
     * Decode and validate. Always throws on parse errors and validation failures.
     *
     * @throws InvalidTokenException       When the token cannot be parsed.
     * @throws JWTConfigurationException   When the library is misconfigured (bad signer/keys/constraints).
     * @throws RequiredConstraintsViolated When a constraint fails.
     */
    public function decode(string $token): Plain
    {
        $configuration = $this->buildConfiguration();

        try {
            $parsed = $configuration->parser()->parse($token);
        } catch (Throwable $e) {
            throw new InvalidTokenException('Token is malformed: ' . $e->getMessage(), 0, $e);
        }

        if (! $parsed instanceof Plain) {
            throw new InvalidTokenException('Only Plain tokens are supported.');
        }

        if ($this->config->validate) {
            $configuration->validator()->assert($parsed, ...$this->buildValidationConstraints());
        } else {
            $this->logValidationDisabledWarning();
        }

        return $parsed;
    }

    /**
     * Decode + validate without throwing on *token* failures (malformed token or
     * a failed constraint) — those return null. A `JWTConfigurationException`
     * (e.g. a misconfigured `$validateClaims`) is deliberately NOT swallowed: a
     * library misconfiguration must surface loudly instead of masquerading as an
     * invalid token, which would otherwise make every valid token look invalid.
     *
     * @throws JWTConfigurationException When the library itself is misconfigured.
     */
    public function tryDecode(string $token): ?Plain
    {
        try {
            return $this->decode($token);
        } catch (InvalidTokenException|RequiredConstraintsViolated) {
            return null;
        }
    }

    /**
     * Validate the token and return the original payload value.
     *
     * Symmetric to `encode()`:
     *   - Scalar / split-mode tokens → raw claim value.
     *   - Compact-mode tokens (header `cty=json`) → `json_decode`d back into an array.
     *
     * @throws InvalidTokenException
     * @throws RequiredConstraintsViolated
     */
    public function getPayload(string $token): mixed
    {
        $parsed = $this->decode($token);
        $value  = $parsed->claims()->get($this->paramData);

        if ($parsed->headers()->get('cty') === 'json' && is_string($value)) {
            return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        }

        return $value;
    }

    /**
     * Full validity check: verifies the signature AND every configured claim.
     *
     * @throws JWTConfigurationException When the library is misconfigured.
     */
    public function isValid(string $token): bool
    {
        return $this->tryDecode($token) instanceof Plain;
    }

    /**
     * Cheap pre-flight check of the `exp` claim.
     *
     * WARNING: this parses the token WITHOUT verifying its signature, so the
     * `exp` value is attacker-controlled. Never use the result to drive an
     * authentication or authorization decision — use `decode()`/`tryDecode()`/
     * `isValid()` for that. Returns true for a token that cannot be parsed or
     * has no `exp` claim is treated as not-expired (false).
     */
    public function isExpired(string $token): bool
    {
        $parsed = $this->parseWithoutValidation($token);
        if (! $parsed instanceof Plain) {
            return true;
        }

        $exp = $parsed->claims()->get('exp');
        if (! $exp instanceof DateTimeImmutable) {
            return false;
        }

        return $exp->getTimestamp() < time();
    }

    /**
     * Seconds until the `exp` claim, or null when unknown.
     *
     * WARNING: like `isExpired()`, this parses WITHOUT verifying the signature;
     * the returned TTL is attacker-controlled and must not gate access decisions.
     */
    public function getTimeToExpiry(string $token): ?int
    {
        $parsed = $this->parseWithoutValidation($token);
        if (! $parsed instanceof Plain) {
            return null;
        }

        $exp = $parsed->claims()->get('exp');
        if (! $exp instanceof DateTimeImmutable) {
            return null;
        }

        return max(0, $exp->getTimestamp() - time());
    }

    /**
     * Inspect claims without validation.
     *
     * Logs a warning unless `Config\JWT::$allowUnsafeExtraction === true` so accidental
     * production usage shows up in logs.
     *
     * @return array<string, mixed>|null Null when the token cannot be parsed.
     */
    public function extractClaimsUnsafe(string $token): ?array
    {
        if (! $this->config->allowUnsafeExtraction) {
            $this->logUnsafeExtractionWarning();
        }

        $parsed = $this->parseWithoutValidation($token);

        return $parsed?->claims()->all();
    }

    private function parseWithoutValidation(string $token): ?Plain
    {
        if ($token === '') {
            return null;
        }

        // Use a key-less parser: inspection (isExpired/getTimeToExpiry/
        // extractClaimsUnsafe) needs no signing config, so a configuration error
        // must not be reinterpreted as "expired"/null. Only genuinely malformed
        // input is swallowed here.
        try {
            $parsed = (new Parser(new JoseEncoder()))->parse($token);
        } catch (Throwable) {
            return null;
        }

        return $parsed instanceof Plain ? $parsed : null;
    }

    /**
     * Write the user payload onto the builder according to the split/compact mode.
     */
    private function applyPayload(Builder $builder, mixed $data): Builder
    {
        if (is_array($data) || is_object($data)) {
            if ($this->split) {
                return $this->applySplitPayload($builder, $data);
            }

            // Compact mode: nest the payload as JSON under the paramData claim and
            // tag cty=json so getPayload() can auto-decode it.
            return $builder
                ->withClaim($this->paramData, json_encode($data, JSON_THROW_ON_ERROR))
                ->withHeader('cty', 'json');
        }

        return $builder->withClaim($this->paramData, $data);
    }

    /**
     * @param array<array-key, mixed>|object $data
     */
    private function applySplitPayload(Builder $builder, array|object $data): Builder
    {
        /** @var iterable<string, mixed> $iterable */
        $iterable = is_object($data) ? get_object_vars($data) : $data;

        foreach ($iterable as $key => $value) {
            $claimName = (string) $key;

            try {
                $builder = $builder->withClaim($claimName, $value);
            } catch (RegisteredClaimGiven $e) {
                // Turn lcobucci's raw error into a library exception that names the
                // offending key and points to compact mode.
                throw JWTConfigurationException::reservedClaimInSplitMode($claimName, $e);
            }
        }

        return $builder;
    }

    private function dataHasUidKey(mixed $data): bool
    {
        if (is_array($data)) {
            return array_key_exists('uid', $data);
        }

        if (is_object($data)) {
            return array_key_exists('uid', get_object_vars($data));
        }

        return false;
    }

    private function buildConfiguration(): Configuration
    {
        // Memoize the stateless signer + key configuration for this immutable
        // instance: decode() builds it once and the SignedWith constraint reuses
        // the same instance, so we no longer rebuild the Configuration twice per
        // call nor re-read the asymmetric PEM from disk. The misconfiguration
        // guards (missingSigner / algorithmMismatch / missingClaim) still run on
        // the first build. This caches only the key material — the time-dependent
        // LooseValidAt / StrictValidAt constraints are deliberately rebuilt per
        // call (see buildValidationConstraints()).
        return $this->configuration ??= match (AlgorithmType::tryFrom($this->config->algorithmType)) {
            AlgorithmType::Symmetric  => $this->buildSymmetricConfiguration(),
            AlgorithmType::Asymmetric => $this->buildAsymmetricConfiguration(),
            default                   => throw JWTConfigurationException::invalidAlgorithmType($this->config->algorithmType),
        };
    }

    private function buildSymmetricConfiguration(): Configuration
    {
        $secret = $this->config->signer;
        if ($secret === null || $secret === '') {
            throw JWTConfigurationException::missingSigner();
        }

        $signer = $this->buildSigner();
        if (! $signer instanceof Hmac) {
            throw JWTConfigurationException::algorithmMismatch('symmetric', $this->config->algorithm);
        }

        return Configuration::forSymmetricSigner(
            $signer,
            InMemory::base64Encoded($secret),
        );
    }

    private function buildAsymmetricConfiguration(): Configuration
    {
        $signingKey   = $this->requireClaim($this->config->signingKey, 'signingKey');
        $verifyingKey = $this->requireClaim($this->config->verifyingKey, 'verifyingKey');

        $signer = $this->buildSigner();
        if (! $signer instanceof OpenSSL) {
            throw JWTConfigurationException::algorithmMismatch('asymmetric', $this->config->algorithm);
        }

        return Configuration::forAsymmetricSigner(
            $signer,
            $this->loadKey($signingKey, $this->config->passphrase ?? ''),
            $this->loadKey($verifyingKey, ''),
        );
    }

    private function buildSigner(): Signer
    {
        $signerClass = $this->config->algorithm;

        return new $signerClass();
    }

    private function loadKey(string $reference, string $passphrase): Key
    {
        if (str_starts_with($reference, 'file://')) {
            return InMemory::file(substr($reference, 7), $passphrase);
        }

        if (! str_contains($reference, "\n") && file_exists($reference) && is_readable($reference)) {
            return InMemory::file($reference, $passphrase);
        }

        return InMemory::plainText($reference, $passphrase);
    }

    /**
     * @return list<Constraint>
     */
    private function buildValidationConstraints(): array
    {
        if (! in_array(ConstraintName::SignedWith->value, $this->config->validateClaims, true)) {
            throw JWTConfigurationException::missingSignatureConstraint();
        }

        $constraints = [];

        foreach ($this->config->validateClaims as $name) {
            $constraint = ConstraintName::fromName($name)
                ?? throw JWTConfigurationException::unknownConstraint($name);

            $constraints[] = match ($constraint) {
                ConstraintName::SignedWith => $this->buildSignedWithConstraint(),
                ConstraintName::IssuedBy   => new IssuedBy(
                    $this->requireClaim($this->config->issuer, 'issuer'),
                ),
                ConstraintName::IdentifiedBy => new IdentifiedBy(
                    $this->requireClaim($this->config->identifier, 'identifier'),
                ),
                ConstraintName::PermittedFor => new PermittedFor(
                    $this->requireClaim($this->config->audience, 'audience'),
                ),
                ConstraintName::StrictValidAt => new StrictValidAt(
                    SystemClock::fromUTC(),
                    $this->buildLeewayInterval(),
                ),
                ConstraintName::LooseValidAt => new LooseValidAt(
                    SystemClock::fromUTC(),
                    $this->buildLeewayInterval(),
                ),
            };
        }

        return $constraints;
    }

    private function buildSignedWithConstraint(): SignedWith
    {
        $configuration = $this->buildConfiguration();

        return new SignedWith($configuration->signer(), $configuration->verificationKey());
    }

    /**
     * Resolve a required string claim, rejecting both `null` and `''`.
     *
     * @return non-empty-string
     */
    private function requireClaim(?string $value, string $name): string
    {
        if ($value === null || $value === '') {
            throw JWTConfigurationException::missingClaim($name);
        }

        return $value;
    }

    /**
     * Apply a `DateTimeImmutable::modify()` modifier, normalising the cross-version
     * failure modes (PHP < 8.3 returns `false`, PHP >= 8.3 throws) into a single,
     * descriptive exception. Both `canOnlyBeUsedAfter` and `expiresAt` go through here
     * so an invalid modifier fails loudly and consistently.
     */
    private function applyModifier(DateTimeImmutable $base, string $modifier, string $name): DateTimeImmutable
    {
        try {
            $result = $base->modify($modifier);
        } catch (Throwable $e) {
            throw $this->invalidModifier($name, $modifier, $e);
        }

        // PHP < 8.3 returns false instead of throwing on an invalid modifier.
        if ($result === false) {
            throw $this->invalidModifier($name, $modifier);
        }

        return $result;
    }

    private function invalidModifier(string $name, string $modifier, ?Throwable $previous = null): InvalidArgumentException
    {
        return new InvalidArgumentException(
            "Config::\${$name} is not a valid DateTimeImmutable modifier: \"{$modifier}\".",
            0,
            $previous,
        );
    }

    private function buildLeewayInterval(): ?DateInterval
    {
        if ($this->leewaySeconds === null || $this->leewaySeconds <= 0) {
            return null;
        }

        return new DateInterval('PT' . $this->leewaySeconds . 'S');
    }

    private function logUidCollisionWarning(): void
    {
        $this->logWarning(
            'Daycry\\JWT\\JWT::encode() in split mode: the framework "uid" claim '
            . 'overwrote a same-named key in the payload data.',
        );
    }

    private function logUnsafeExtractionWarning(): void
    {
        $this->logWarning(
            'Daycry\\JWT\\JWT::extractClaimsUnsafe() was called without setting '
            . 'Config\\JWT::$allowUnsafeExtraction = true. The token has not been validated.',
        );
    }

    private function logValidationDisabledWarning(): void
    {
        $this->logWarning(
            'Daycry\\JWT\\JWT::decode() ran with Config\\JWT::$validate = false. '
            . 'The token signature and registered claims were NOT verified.',
        );
    }

    private function logWarning(string $message): void
    {
        if (! function_exists('log_message')) {
            return;
        }

        log_message('warning', $message);
    }
}
