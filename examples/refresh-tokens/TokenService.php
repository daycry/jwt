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

use Daycry\JWT\Config\JWT as JWTConfig;
use Daycry\JWT\Exceptions\InvalidTokenException;
use Daycry\JWT\JWT;
use Lcobucci\JWT\Validation\RequiredConstraintsViolated;

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
        $jwt    = $this->buildJwt($this->refreshConfig());
        $parsed = $jwt->decode($refreshToken); // throws on bad input — let it propagate

        if ($parsed->claims()->get('type') !== 'refresh') {
            throw new InvalidTokenException('Token is not a refresh token.');
        }

        $jti = (string) $parsed->claims()->get('jti');
        if ($this->usedRefreshTokens->isUsed($jti)) {
            throw new RequiredConstraintsViolated([], 'Refresh token already consumed');
        }

        $exp        = $parsed->claims()->get('exp');
        $remaining  = max(60, $exp->getTimestamp() - time());
        $this->usedRefreshTokens->markUsed($jti, $remaining);

        $userId = (string) $parsed->claims()->get('uid');

        return $this->issuePair($userId);
    }

    private function issueAccess(string $userId): string
    {
        $config            = config('JWT');
        $config->expiresAt = '+15 minutes';

        return $this->buildJwt($config)->encode(['type' => 'access'], $userId);
    }

    private function issueRefresh(string $userId): string
    {
        $config = $this->refreshConfig();

        return $this->buildJwt($config)->encode(['type' => 'refresh'], $userId);
    }

    private function refreshConfig(): JWTConfig
    {
        $config             = config('JWT');
        $config->expiresAt  = '+30 days';
        // A unique jti per issuance prevents reuse:
        $config->identifier = bin2hex(random_bytes(16));

        return $config;
    }

    private function buildJwt(JWTConfig $config): JWT
    {
        return new JWT($config);
    }
}
