<?php

namespace Daycry\JWT;

use DateTimeImmutable;
use Daycry\JWT\Config\JWT as JWTConfig;
use Lcobucci\Clock\FrozenClock;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token\DataSet;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Validation\Constraint\IdentifiedBy;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\PermittedFor;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Constraint\ValidAt;
use Lcobucci\JWT\Validation\RequiredConstraintsViolated;

class JWT
{
    private ?JWTConfig $JWTConfig = null;

    /**
     * Configuration Class
     */
    private ?Configuration $configuration = null;

    /**
     * Split data if array
     */
    private bool $split = false;

    /**
     * Name of attribute of data
     */
    private string $paramData = 'data';

    /**
     * Cache for validation constraints
     */
    private array $constraintsCache = [];

    public function __construct(?JWTConfig $config = null)
    {
        $this->JWTConfig = $config;
    }

    /**
     * Get JWT configuration with lazy loading
     */
    private function getConfig(): JWTConfig
    {
        if ($this->JWTConfig === null) {
            $this->JWTConfig = config('JWT');
        }

        return $this->JWTConfig;
    }

    /**
     * Get JWT configuration with lazy loading
     */
    private function getConfiguration(): Configuration
    {
        if ($this->configuration === null) {
            $config = $this->getConfig();
            $this->configuration = Configuration::forSymmetricSigner(
                new $config->algorithm(),
                InMemory::base64Encoded($config->signer),
            );
        }

        return $this->configuration;
    }

    public function getParamData()
    {
        return $this->paramData;
    }

    /**
     * Set the attibute to data claim
     * Used if data is not an array
     */
    public function setParamData(string $data): JWT
    {
        $this->paramData = $data;

        return $this;
    }

    public function setSplitData(bool $value = true): JWT
    {
        $this->split = $value;

        return $this;
    }

    public function encode($data, $uid = null): string
    {
        $now = new DateTimeImmutable();
        $config = $this->getConfig();
        $configuration = $this->getConfiguration();

        $token = $configuration->builder();

        if (is_array($data) || is_object($data)) {
            if ($this->split) {
                foreach ($data as $key => $value) {
                    $token->withClaim($key, $value);
                }
            } else {
                $token->withClaim($this->paramData, \json_encode($data, JSON_THROW_ON_ERROR));
            }
        } else {
            $token->withClaim($this->paramData, $data);
        }

        $uid = $uid ?? $config->uid;

        // Configures a new claim, called "uid"
        if ($uid) {
            $token->withClaim('uid', $uid);
        }

        // Add a small delay to ensure token times are not in the future
        $notBefore = $now->modify($config->canOnlyBeUsedAfter);
        if ($notBefore > $now) {
            $notBefore = $now;
        }

        // Configures the issuer (iss claim)
        $token->issuedBy($config->issuer)
            // Configures the audience (aud claim)
            ->permittedFor($config->audience)
            // Configures the id (jti claim)
            ->identifiedBy($config->identifier)
            // Configures the time that the token was issue (iat claim)
            ->issuedAt($now)
            // Configures the time that the token can be used (nbf claim)
            ->canOnlyBeUsedAfter($notBefore)
            // Configures the expiration time of the token (exp claim)
            ->expiresAt($now->modify($config->expiresAt))
            ->withHeader('type', 'Bearer');

        // Builds a new token;
        $token = $token->getToken($configuration->signer(), $configuration->signingKey());

        return $token->toString();
    }

    public function decode($data): DataSet|RequiredConstraintsViolated
    {
        $configuration = $this->getConfiguration();
        $config = $this->getConfig();

        /** @var Plain $token */
        $token = $configuration->parser()->parse($data);

        try {
            // Validates the token signature
            if ($config->validate === true) {
                $constraints = $this->getValidationConstraints();
                $configuration->validator()->assert($token, ...$constraints);
            }
        } catch (RequiredConstraintsViolated $e) {
            if ($config->throwable) {
                throw $e;
            }

            return $e;
        }

        return $token->claims();
    }

    /**
     * Get validation constraints with caching
     */
    private function getValidationConstraints(): array
    {
        $config = $this->getConfig();
        $constraintsKey = md5(serialize($config->validateClaims));

        if (!isset($this->constraintsCache[$constraintsKey])) {
            $this->constraintsCache[$constraintsKey] = $this->buildValidationConstraints($config);
        }

        return $this->constraintsCache[$constraintsKey];
    }

    /**
     * Build validation constraints dynamically based on configuration
     */
    private function buildValidationConstraints(?JWTConfig $config = null): array
    {
        $constraints = [];
        $clock       = new FrozenClock(new DateTimeImmutable());
        $config      = $config ?? $this->getConfig();
        $configuration = $this->getConfiguration();

        foreach ($config->validateClaims as $constraintName) {
            switch ($constraintName) {
                case 'SignedWith':
                    $constraints[] = new SignedWith(
                        $configuration->signer(),
                        $configuration->signingKey(),
                    );
                    break;

                case 'IssuedBy':
                    $constraints[] = new IssuedBy($config->issuer);
                    break;

                case 'ValidAt':
                    // Use current time for validation to avoid "issued in future" errors
                    $constraints[] = new ValidAt(new FrozenClock(new DateTimeImmutable()));
                    break;

                case 'IdentifiedBy':
                    $constraints[] = new IdentifiedBy($config->identifier);
                    break;

                case 'PermittedFor':
                    $constraints[] = new PermittedFor($config->audience);
                    break;
            }
        }

        return $constraints;
    }

    /**
     * Quick validation without full decoding (performance optimized)
     */
    public function isValid(string $token): bool
    {
        try {
            $configuration = $this->getConfiguration();
            /** @var Plain $parsedToken */
            $parsedToken = $configuration->parser()->parse($token);
            
            $config = $this->getConfig();
            if ($config->validate) {
                $constraints = $this->getValidationConstraints();
                $configuration->validator()->assert($parsedToken, ...$constraints);
            }
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Extract claims without validation for performance-critical scenarios
     */
    public function extractClaimsUnsafe(string $token): ?array
    {
        try {
            $configuration = $this->getConfiguration();
            /** @var Plain $parsedToken */
            $parsedToken = $configuration->parser()->parse($token);
            return $parsedToken->claims()->all();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Check if token is expired without full validation
     */
    public function isExpired(string $token): bool
    {
        try {
            $configuration = $this->getConfiguration();
            /** @var Plain $parsedToken */
            $parsedToken = $configuration->parser()->parse($token);
            $exp = $parsedToken->claims()->get('exp');
            
            if ($exp === null) {
                return false;
            }
            
            return $exp->getTimestamp() < time();
        } catch (\Exception $e) {
            return true;
        }
    }

    /**
     * Get time to expiry in seconds
     */
    public function getTimeToExpiry(string $token): ?int
    {
        try {
            $configuration = $this->getConfiguration();
            /** @var Plain $parsedToken */
            $parsedToken = $configuration->parser()->parse($token);
            $exp = $parsedToken->claims()->get('exp');
            
            if ($exp === null) {
                return null;
            }
            
            $remaining = $exp->getTimestamp() - time();
            return max(0, $remaining);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Clear constraints cache (useful for testing or configuration changes)
     */
    public function clearCache(): void
    {
        $this->constraintsCache = [];
    }
}
