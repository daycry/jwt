<?php

namespace Daycry\JWT\Exceptions;

use RuntimeException;

class JWTConfigurationException extends RuntimeException
{
    public static function missingSigner(): self
    {
        return new self(
            'JWT signing key is not configured. Run "php spark jwt:key" to generate one '
            . 'and ensure it is set in your .env file as "jwt.signer", or assign it directly '
            . 'on Daycry\\JWT\\Config\\JWT::$signer.',
        );
    }

    public static function missingClaim(string $name): self
    {
        return new self(
            "JWT \"{$name}\" is not configured. Set Daycry\\JWT\\Config\\JWT::\${$name} "
            . "or the corresponding env variable \"jwt.{$name}\" before encoding tokens.",
        );
    }

    public static function invalidAlgorithmType(string $value): self
    {
        return new self(sprintf(
            'Daycry\\JWT\\Config\\JWT::$algorithmType must be "symmetric" or "asymmetric"; "%s" given.',
            $value,
        ));
    }

    public static function unknownConstraint(string $name): self
    {
        return new self(sprintf(
            'Unknown validation constraint "%s" in Daycry\\JWT\\Config\\JWT::$validateClaims. '
            . 'Allowed: SignedWith, IssuedBy, LooseValidAt, StrictValidAt, IdentifiedBy, PermittedFor.',
            $name,
        ));
    }
}
