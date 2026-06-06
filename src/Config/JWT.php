<?php

declare(strict_types=1);

namespace Daycry\JWT\Config;

use CodeIgniter\Config\BaseConfig;
use Lcobucci\JWT\Signer;
use Lcobucci\JWT\Signer\Hmac\Sha256;

class JWT extends BaseConfig
{
    /**
     * Default `uid` claim — a string or integer ID. May be overridden per-call via
     * `JWT::encode($data, $uid)`.
     */
    public int|string|null $uid = null;

    /**
     * Algorithm family. Drives how `signer`/`signingKey`/`verifyingKey` are interpreted.
     *
     * Allowed values:
     *   - 'symmetric'  → HMAC, uses `$signer` (base64 secret).
     *   - 'asymmetric' → RSA / ECDSA, uses `$signingKey` + `$verifyingKey` (PEM contents or paths).
     */
    public string $algorithmType = 'symmetric';

    /**
     * Signer class for the chosen `$algorithmType`.
     *
     * Symmetric:
     *   \Lcobucci\JWT\Signer\Hmac\Sha256::class
     *   \Lcobucci\JWT\Signer\Hmac\Sha384::class
     *   \Lcobucci\JWT\Signer\Hmac\Sha512::class
     * Asymmetric (RSA):
     *   \Lcobucci\JWT\Signer\Rsa\Sha256::class (RS256)
     *   \Lcobucci\JWT\Signer\Rsa\Sha384::class (RS384)
     *   \Lcobucci\JWT\Signer\Rsa\Sha512::class (RS512)
     * Asymmetric (ECDSA):
     *   \Lcobucci\JWT\Signer\Ecdsa\Sha256::class (ES256)
     *   \Lcobucci\JWT\Signer\Ecdsa\Sha384::class (ES384)
     *   \Lcobucci\JWT\Signer\Ecdsa\Sha512::class (ES512)
     *
     * @var class-string<Signer>
     */
    public string $algorithm = Sha256::class;

    /**
     * Base64-encoded HMAC secret. Required when `$algorithmType = 'symmetric'`.
     * Generate with `php spark jwt:key`.
     */
    public ?string $signer = null;

    /**
     * Path to the private signing key (PEM file) or its raw contents.
     * Required when `$algorithmType = 'asymmetric'`. Generate with `php spark jwt:keypair`.
     */
    public ?string $signingKey = null;

    /**
     * Path to the public verification key (PEM file) or its raw contents.
     * Required when `$algorithmType = 'asymmetric'`.
     */
    public ?string $verifyingKey = null;

    /**
     * Passphrase that protects the private signing key, if any.
     */
    public ?string $passphrase = null;

    public ?string $issuer     = null;
    public ?string $audience   = null;
    public ?string $identifier = null;

    /**
     * Modifier accepted by `DateTimeImmutable::modify()`.
     */
    public string $canOnlyBeUsedAfter = '+0 minute';

    /**
     * Modifier accepted by `DateTimeImmutable::modify()`.
     */
    public string $expiresAt = '+24 hour';

    /**
     * Acceptable clock skew in seconds when validating `iat` / `nbf` / `exp`.
     * Backed by `LooseValidAt`. Set to `null` for no leeway.
     */
    public ?int $leeway = 0;

    /**
     * When false, `decode()` skips validation entirely. Use only in tests / debug.
     */
    public bool $validate = true;

    /**
     * Allow `JWT::extractClaimsUnsafe()` without runtime warnings.
     * Leaving `false` makes the library log a warning each call to flag accidental
     * production usage.
     */
    public bool $allowUnsafeExtraction = false;

    /**
     * Active validation constraints. Allowed values: 'SignedWith', 'IssuedBy',
     * 'IdentifiedBy', 'PermittedFor', 'LooseValidAt', 'StrictValidAt'
     * ('ValidAt' is a legacy alias for 'LooseValidAt'). See
     * {@see \Daycry\JWT\Enums\ConstraintName}.
     *
     * @var list<string>
     */
    public array $validateClaims = [
        'SignedWith',
        'IssuedBy',
        'LooseValidAt',
        'IdentifiedBy',
        'PermittedFor',
    ];
}
