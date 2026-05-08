# Configuration

> **[← Back to index](index.md)**

---

## Overview

`Daycry\JWT\Config\JWT` extends CodeIgniter's `BaseConfig`. After running `php spark jwt:publish` you get an editable copy at `app/Config/JWT.php`.

All values are also overridable through `.env` using the standard CI4 dot-notation convention: `jwt.{property}`.

The library refuses to operate when required fields are missing — it throws `Daycry\JWT\Exceptions\JWTConfigurationException`. There are **no insecure defaults**.

---

## Required fields

| Property | Required for | Default |
|---|---|---|
| `$signer` | symmetric (HMAC) | `null` |
| `$signingKey` + `$verifyingKey` | asymmetric (RSA / ECDSA) | `null` |
| `$issuer` | always | `null` |
| `$audience` | always | `null` |
| `$identifier` | always | `null` |

The first call to `encode()` / `decode()` with any of these unset throws `JWTConfigurationException::missingClaim('...')` with a message that names the missing field.

---

## Algorithm selection

### `$algorithmType`

`'symmetric'` (default) or `'asymmetric'`. Drives which key fields are read.

### `$algorithm`

The signer class. Pick one matching `$algorithmType`:

| Algorithm | Class |
|---|---|
| HMAC SHA-256 (HS256) | `\Lcobucci\JWT\Signer\Hmac\Sha256::class` |
| HMAC SHA-384 (HS384) | `\Lcobucci\JWT\Signer\Hmac\Sha384::class` |
| HMAC SHA-512 (HS512) | `\Lcobucci\JWT\Signer\Hmac\Sha512::class` |
| RSA SHA-256 (RS256) | `\Lcobucci\JWT\Signer\Rsa\Sha256::class` |
| RSA SHA-384 (RS384) | `\Lcobucci\JWT\Signer\Rsa\Sha384::class` |
| RSA SHA-512 (RS512) | `\Lcobucci\JWT\Signer\Rsa\Sha512::class` |
| ECDSA P-256 (ES256) | `\Lcobucci\JWT\Signer\Ecdsa\Sha256::class` |
| ECDSA P-384 (ES384) | `\Lcobucci\JWT\Signer\Ecdsa\Sha384::class` |
| ECDSA P-521 (ES512) | `\Lcobucci\JWT\Signer\Ecdsa\Sha512::class` |

---

## Symmetric (HMAC) keys

### `$signer`

Base64-encoded secret. Generate one with:

```bash
php spark jwt:key             # writes to .env
php spark jwt:key 64 --show   # 64-byte key, print only
```

```ini
jwt.signer = "<paste-the-key-from-jwt:key>"
```

> Rotating the key invalidates **all** outstanding tokens.

---

## Asymmetric (RSA / ECDSA) keys

### `$signingKey` / `$verifyingKey`

Either a filesystem path (preferred) or the raw PEM contents. Generate with:

```bash
php spark jwt:keypair --algorithm=rsa   --bits=2048
php spark jwt:keypair --algorithm=ecdsa --curve=prime256v1
```

```ini
jwt.algorithmType = "asymmetric"
jwt.signingKey    = "/var/www/app/writable/keys/jwt-private.pem"
jwt.verifyingKey  = "/var/www/app/writable/keys/jwt-public.pem"
```

A path under `WRITEPATH` works out of the box; alternatively prefix with `file://` or pass the literal PEM string.

### `$passphrase`

Set if the private key is encrypted. Used only when reading `$signingKey`. Leave `null` for unencrypted keys.

---

## Claims

### `$issuer` (`iss`)

URL of the token-issuing service.

### `$audience` (`aud`)

URL of the consuming service. Tokens whose `aud` does not include this value are rejected.

### `$identifier` (`jti`)

Application-specific identifier. Tokens with a different `jti` are rejected.

### `$uid`

Default value for the custom `uid` claim. Set per-call via `JWT::encode($data, $uid)`.

### `$canOnlyBeUsedAfter` / `$expiresAt`

`DateTimeImmutable::modify()` strings. Defaults: `+0 minute` / `+24 hour`.

```php
public string $expiresAt = '+15 minutes'; // short-lived access token
```

If `canOnlyBeUsedAfter` resolves to a moment after `iat`, it is clamped to `iat` so freshly-issued tokens are immediately usable.

---

## Validation

### `$validate`

`true` (default): `decode()` runs every constraint in `$validateClaims`.
`false`: parsing only — **never use in production**.

### `$validateClaims`

Ordered list of constraint names. Defaults: `['SignedWith', 'IssuedBy', 'LooseValidAt', 'IdentifiedBy', 'PermittedFor']`.

Allowed values:

| Name | Library class | Notes |
|---|---|---|
| `SignedWith` | `Lcobucci\JWT\Validation\Constraint\SignedWith` | Signature verification |
| `IssuedBy` | `IssuedBy` | `iss` |
| `IdentifiedBy` | `IdentifiedBy` | `jti` |
| `PermittedFor` | `PermittedFor` | `aud` |
| `LooseValidAt` (default) | `LooseValidAt` | `iat`/`nbf`/`exp` with leeway, missing claims tolerated |
| `StrictValidAt` | `StrictValidAt` | `iat`/`nbf`/`exp` all required |
| `ValidAt` | alias of `LooseValidAt` | Provided for v1.x compatibility |

### `$leeway`

Acceptable clock skew in seconds for `LooseValidAt` / `StrictValidAt`. `0` (default) means strict; `null` also disables leeway.

```php
public ?int $leeway = 30;   // accept ±30 s of skew
```

Leeway can also be applied per-call: `$jwt->withLeeway(60)`.

---

## Other flags

### `$allowUnsafeExtraction`

`false` (default): every call to `JWT::extractClaimsUnsafe()` writes a warning to the framework logger so accidental production use shows up. Set to `true` if you intentionally rely on the method (token inspection tools, etc.).

---

## `.env` reference

```ini
# Algorithm
jwt.algorithmType = "symmetric"
jwt.algorithm     = "Lcobucci\\JWT\\Signer\\Hmac\\Sha256"

# Symmetric secret
jwt.signer        = "<base64-secret>"

# Asymmetric (when algorithmType=asymmetric)
jwt.signingKey    = "/path/to/private.pem"
jwt.verifyingKey  = "/path/to/public.pem"
jwt.passphrase    = ""

# Claims
jwt.issuer        = "https://api.my-app.com"
jwt.audience      = "https://my-app.com"
jwt.identifier    = "my-app-v2"

# Lifetime
jwt.canOnlyBeUsedAfter = "+0 minute"
jwt.expiresAt          = "+1 hour"

# Validation
jwt.validate              = true
jwt.leeway                = 30
jwt.allowUnsafeExtraction = false
```

---

## Programmatic override

```php
use Daycry\JWT\JWT;

$config            = config('JWT');
$config->expiresAt = '+5 minutes';
$config->uid       = (string) $userId;

$token = (new JWT($config))->encode($payload);
```
