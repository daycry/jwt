<?php

/**
 * Access + refresh token issuance and rotation.
 *
 * Strategy:
 *   - Access tokens are short-lived (15 minutes) and used for every API call.
 *   - Refresh tokens are long-lived (30 days) and used only against /auth/refresh.
 *   - Each refresh token carries a `type=refresh` claim and a unique `jti`.
 *   - When `refresh()` succeeds, the previous refresh token's `jti` is recorded
 *     in a one-shot store (Redis / DB), invalidating it for replay.
 *
 * The store is intentionally abstract — adapt `RefreshTokenStore` to your stack.
 */

namespace App\Services;

use Daycry\JWT\Exceptions\InvalidTokenException;
use Daycry\JWT\JWT;

interface RefreshTokenStore
{
    public function isUsed(string $jti): bool;

    public function markUsed(string $jti, int $ttlSeconds): void;
}

final class TokenService
{
    public function __construct(
        private readonly RefreshTokenStore $usedRefreshTokens,
    ) {
    }

    /** @return array{access: string, refresh: string} */
    public function issuePair(string $userId): array
    {
        return [
            'access'  => $this->issueAccess($userId),
            'refresh' => $this->issueRefresh($userId),
        ];
    }

    /**
     * Verify a refresh token, rotate it (mark the old jti as used), and issue
     * a new access + refresh pair.
     *
     * @return array{access: string, refresh: string}
     */
    public function refresh(string $refreshToken): array
    {
        // Verify the signature + standard claims BEFORE trusting any claim. The
        // jti is unique per refresh token (one-time use), so IdentifiedBy is
        // intentionally NOT asserted here — replay is tracked via the store below.
        // Clone the config so the shared config('JWT') singleton is never mutated.
        $config                 = clone config('JWT');
        $config->validateClaims = ['SignedWith', 'IssuedBy', 'PermittedFor', 'LooseValidAt'];

        $parsed = (new JWT($config))->decode($refreshToken); // throws on bad input — let it propagate

        if ($parsed->claims()->get('type') !== 'refresh') {
            throw new InvalidTokenException('Token is not a refresh token.');
        }

        $jti = (string) $parsed->claims()->get('jti');
        if ($this->usedRefreshTokens->isUsed($jti)) {
            throw new InvalidTokenException('Refresh token already consumed.');
        }

        $exp       = $parsed->claims()->get('exp');
        $remaining = max(60, $exp->getTimestamp() - time());
        $this->usedRefreshTokens->markUsed($jti, $remaining);

        $userId = (string) $parsed->claims()->get('uid');

        return $this->issuePair($userId);
    }

    private function issueAccess(string $userId): string
    {
        // Per-instance override — never mutate the shared config('JWT') singleton.
        return JWT::for()->withExpiresAt('+15 minutes')->encode(['type' => 'access'], $userId);
    }

    private function issueRefresh(string $userId): string
    {
        // A refresh token needs a longer lifetime AND a unique jti per issuance,
        // which withExpiresAt() alone cannot express — clone the config instead
        // of mutating the shared singleton.
        $config             = clone config('JWT');
        $config->expiresAt  = '+30 days';
        $config->identifier = bin2hex(random_bytes(16)); // unique jti (one-time use)

        return (new JWT($config))->encode(['type' => 'refresh'], $userId);
    }
}
