<?php

namespace Daycry\JWT;

use DateInterval;
use DateTimeImmutable;
use Daycry\JWT\Config\JWT as JWTConfig;
use Daycry\JWT\Exceptions\InvalidTokenException;
use Daycry\JWT\Exceptions\JWTConfigurationException;
use InvalidArgumentException;
use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token\Plain;
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
    private bool $split = false;

    /**
     * @var non-empty-string
     */
    private string $paramData = 'data';

    private ?int $leewaySeconds;

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
        $clone            = clone $this;
        $clone->paramData = $name;

        return $clone;
    }

    public function withLeeway(int $seconds): self
    {
        if ($seconds < 0) {
            throw new InvalidArgumentException('Leeway cannot be negative.');
        }
        $clone                = clone $this;
        $clone->leewaySeconds = $seconds;

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

    public function encode(mixed $data, mixed $uid = null): string
    {
        $now           = new DateTimeImmutable();
        $configuration = $this->buildConfiguration();

        $issuer     = $this->config->issuer ?? throw JWTConfigurationException::missingClaim('issuer');
        $audience   = $this->config->audience ?? throw JWTConfigurationException::missingClaim('audience');
        $identifier = $this->config->identifier ?? throw JWTConfigurationException::missingClaim('identifier');

        $builder          = $configuration->builder();
        $serializedAsJson = false;

        if (is_array($data) || is_object($data)) {
            if ($this->split) {
                /** @var iterable<string, mixed> $iterable */
                $iterable = is_object($data) ? get_object_vars($data) : $data;

                foreach ($iterable as $key => $value) {
                    $builder = $builder->withClaim((string) $key, $value);
                }
            } else {
                $builder = $builder->withClaim(
                    $this->paramData,
                    json_encode($data, JSON_THROW_ON_ERROR),
                );
                $serializedAsJson = true;
            }
        } else {
            $builder = $builder->withClaim($this->paramData, $data);
        }

        $resolvedUid = $uid ?? $this->config->uid;
        if ($resolvedUid !== null && $resolvedUid !== '') {
            $builder = $builder->withClaim('uid', $resolvedUid);
        }

        if ($serializedAsJson) {
            $builder = $builder->withHeader('cty', 'json');
        }

        $notBefore = $now->modify($this->config->canOnlyBeUsedAfter);
        if ($notBefore === false || $notBefore > $now) {
            $notBefore = $now;
        }

        $expiresAt = $now->modify($this->config->expiresAt);
        if ($expiresAt === false) {
            throw new InvalidArgumentException(
                "Config::\$expiresAt is not a valid DateTimeImmutable modifier: {$this->config->expiresAt}",
            );
        }

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
        }

        return $parsed;
    }

    /**
     * Decode + validate without throwing. Returns null on any failure.
     */
    public function tryDecode(string $token): ?Plain
    {
        try {
            return $this->decode($token);
        } catch (Throwable) {
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

    public function isValid(string $token): bool
    {
        return $this->tryDecode($token) instanceof Plain;
    }

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
        try {
            $parsed = $this->buildConfiguration()->parser()->parse($token);
        } catch (Throwable) {
            return null;
        }

        return $parsed instanceof Plain ? $parsed : null;
    }

    private function buildConfiguration(): Configuration
    {
        return match ($this->config->algorithmType) {
            'symmetric'  => $this->buildSymmetricConfiguration(),
            'asymmetric' => $this->buildAsymmetricConfiguration(),
            default      => throw JWTConfigurationException::invalidAlgorithmType($this->config->algorithmType),
        };
    }

    private function buildSymmetricConfiguration(): Configuration
    {
        if ($this->config->signer === null || $this->config->signer === '') {
            throw JWTConfigurationException::missingSigner();
        }

        $signerClass = $this->config->algorithm;

        return Configuration::forSymmetricSigner(
            new $signerClass(),
            InMemory::base64Encoded($this->config->signer),
        );
    }

    private function buildAsymmetricConfiguration(): Configuration
    {
        if ($this->config->signingKey === null || $this->config->signingKey === '') {
            throw JWTConfigurationException::missingClaim('signingKey');
        }
        if ($this->config->verifyingKey === null || $this->config->verifyingKey === '') {
            throw JWTConfigurationException::missingClaim('verifyingKey');
        }

        $signerClass = $this->config->algorithm;

        return Configuration::forAsymmetricSigner(
            new $signerClass(),
            $this->loadKey($this->config->signingKey, $this->config->passphrase ?? ''),
            $this->loadKey($this->config->verifyingKey, ''),
        );
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
        $constraints = [];

        foreach ($this->config->validateClaims as $name) {
            $constraints[] = match ($name) {
                'SignedWith' => $this->buildSignedWithConstraint(),
                'IssuedBy'   => new IssuedBy(
                    $this->config->issuer ?? throw JWTConfigurationException::missingClaim('issuer'),
                ),
                'IdentifiedBy' => new IdentifiedBy(
                    $this->config->identifier ?? throw JWTConfigurationException::missingClaim('identifier'),
                ),
                'PermittedFor' => new PermittedFor(
                    $this->config->audience ?? throw JWTConfigurationException::missingClaim('audience'),
                ),
                'StrictValidAt' => new StrictValidAt(
                    SystemClock::fromUTC(),
                    $this->buildLeewayInterval(),
                ),
                'ValidAt', 'LooseValidAt' => new LooseValidAt(
                    SystemClock::fromUTC(),
                    $this->buildLeewayInterval(),
                ),
                default => throw JWTConfigurationException::unknownConstraint($name),
            };
        }

        return $constraints;
    }

    private function buildSignedWithConstraint(): SignedWith
    {
        $configuration = $this->buildConfiguration();

        return new SignedWith($configuration->signer(), $configuration->verificationKey());
    }

    private function buildLeewayInterval(): ?DateInterval
    {
        if ($this->leewaySeconds === null || $this->leewaySeconds <= 0) {
            return null;
        }

        return new DateInterval('PT' . $this->leewaySeconds . 'S');
    }

    private function logUnsafeExtractionWarning(): void
    {
        if (! function_exists('log_message')) {
            return;
        }

        log_message(
            'warning',
            'Daycry\\JWT\\JWT::extractClaimsUnsafe() was called without setting '
            . 'Config\\JWT::$allowUnsafeExtraction = true. The token has not been validated.',
        );
    }
}
