<?php

/**
 * Multi-tenant verifier.
 *
 * Use case: the API accepts tokens from N partner issuers, each with their own
 * signing key (HMAC) or public key (RSA/ECDSA). When a request arrives:
 *
 *   1. Read the `iss` claim with `extractClaimsUnsafe()` (no verification yet).
 *   2. Look up the tenant config keyed by `iss`.
 *   3. Build a `JWT` instance with that tenant's config and run `decode()`.
 *
 * `extractClaimsUnsafe()` is the documented hook for this exact pattern — set
 * `Config\JWT::$allowUnsafeExtraction = true` for the inspection instance to
 * silence the warning, but keep it `false` for the verifier instance so any
 * other accidental use stays visible in logs.
 */

namespace App\Services;

use Daycry\JWT\Config\JWT as JWTConfig;
use Daycry\JWT\Exceptions\InvalidTokenException;
use Daycry\JWT\JWT;
use Lcobucci\JWT\Token\Plain;

final class TenantTokenRouter
{
    /**
     * @param array<string, JWTConfig> $tenantsByIssuer Pre-built configs, one per known iss.
     */
    public function __construct(private readonly array $tenantsByIssuer)
    {
    }

    public function verify(string $token): Plain
    {
        $issuer = $this->peekIssuer($token);
        $config = $this->tenantsByIssuer[$issuer] ?? throw new InvalidTokenException(
            "Unknown issuer: {$issuer}",
        );

        return (new JWT($config))->decode($token);
    }

    private function peekIssuer(string $token): string
    {
        // Use a throwaway config that *only* parses headers and claims. Clone it
        // so the request-wide config('JWT') singleton is never left with
        // validation disabled — that would weaken every other verifier this request.
        $inspector                        = clone config('JWT');
        $inspector->allowUnsafeExtraction = true;
        $inspector->validate              = false;

        $claims = (new JWT($inspector))->extractClaimsUnsafe($token);
        if ($claims === null || ! isset($claims['iss']) || ! is_string($claims['iss'])) {
            throw new InvalidTokenException('Token is missing the iss claim.');
        }

        return $claims['iss'];
    }
}
