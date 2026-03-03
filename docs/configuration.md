# Configuration

> **[← Back to index](index.md)**

---

## Overview

Configuration is handled by `Daycry\JWT\Config\JWT` which extends CodeIgniter's `BaseConfig`. After running `php spark jwt:publish` you will find your editable copy at `app/Config/JWT.php`.

---

## Full Reference

### `$signer`

| Type | Default |
|---|---|
| `string` | `'mBC5v1sOKVvbdEitdSBenu59nfNfhwkedkJVNabosTw='` |

**Base64-encoded** symmetric signing key. This is the secret used to sign and verify every token.

Generate a production-grade key with:

```bash
php spark jwt:key        # writes to .env automatically
php spark jwt:key --show # print only, useful for copy-paste
```

Override via `.env`:

```ini
jwt.signer = "your-base64-encoded-secret"
```

> ⚠️ Keep this value secret. Rotate it if you suspect it has been compromised (all existing tokens will immediately become invalid).

---

### `$algorithm`

| Type | Default |
|---|---|
| `string` | `\Lcobucci\JWT\Signer\Hmac\Sha256::class` |

The signing algorithm class. The library supports the following symmetric HMAC algorithms:

| Class | Strength |
|---|---|
| `\Lcobucci\JWT\Signer\Hmac\Sha256::class` | 256-bit (recommended) |
| `\Lcobucci\JWT\Signer\Hmac\Sha384::class` | 384-bit |
| `\Lcobucci\JWT\Signer\Hmac\Sha512::class` | 512-bit |

```php
// app/Config/JWT.php
public string $algorithm = \Lcobucci\JWT\Signer\Hmac\Sha512::class;
```

---

### `$issuer`

| Type | Default |
|---|---|
| `string` | `'http://example.local'` |

The token issuer (`iss` claim). Typically the URL of the service that generates the token.

```php
public string $issuer = 'https://api.my-app.com';
```

---

### `$audience`

| Type | Default |
|---|---|
| `string` | `'http://example.local'` |

The intended audience (`aud` claim). Typically the URL of the service that consumes the token.

```php
public string $audience = 'https://my-app.com';
```

---

### `$identifier`

| Type | Default |
|---|---|
| `string` | `'4f1g23a12aa'` |

A unique identifier for this application (`jti` claim). Change this to something meaningful for your project.

```php
public string $identifier = 'my-app-v2';
```

---

### `$uid`

| Type | Default |
|---|---|
| `?string` | `null` |

Default subject identifier stored as a custom `uid` claim. Can be overridden per token via the second argument of `encode()`. When `null`, no `uid` claim is added unless explicitly provided at encode time.

```php
public ?string $uid = 'global-app-uid';
```

---

### `$canOnlyBeUsedAfter`

| Type | Default |
|---|---|
| `string` | `'+0 minute'` |

The not-before offset (`nbf` claim), expressed as a `DateTimeImmutable::modify()` string. The token cannot be used before `issuedAt + offset`.

```php
public string $canOnlyBeUsedAfter = '+5 seconds'; // grace period
```

> **Note:** If the computed `nbf` timestamp would be in the future relative to `iat`, the library resets it to `iat` to prevent immediate rejection.

---

### `$expiresAt`

| Type | Default |
|---|---|
| `string` | `'+24 hour'` |

Token lifetime (`exp` claim), expressed as a `DateTimeImmutable::modify()` string.

```php
public string $expiresAt = '+1 hour';    // short-lived access token
public string $expiresAt = '+30 days';   // long-lived refresh token
```

---

### `$validate`

| Type | Default |
|---|---|
| `bool` | `true` |

When `true`, `decode()` runs all constraints in `$validateClaims` against the token. Set to `false` to parse tokens without any validation (e.g. for debugging).

```php
public bool $validate = false; // disable validation globally
```

---

### `$throwable`

| Type | Default |
|---|---|
| `bool` | `true` |

Controls what happens when validation fails inside `decode()`:

| Value | Behaviour |
|---|---|
| `true` | Throws `Lcobucci\JWT\Validation\RequiredConstraintsViolated` |
| `false` | Returns the exception object instead of throwing it |

```php
public bool $throwable = false; // return the error, don't throw
```

---

### `$validateClaims`

| Type | Default |
|---|---|
| `array` | `['SignedWith', 'IssuedBy', 'ValidAt', 'IdentifiedBy', 'PermittedFor']` |

An ordered list of constraint names to evaluate during `decode()`. Each string maps to one of the following:

| Name | Validates |
|---|---|
| `'SignedWith'` | Token signature matches the configured key and algorithm |
| `'IssuedBy'` | `iss` claim equals `$issuer` |
| `'ValidAt'` | Current time is inside `[nbf, exp]` window |
| `'IdentifiedBy'` | `jti` claim equals `$identifier` |
| `'PermittedFor'` | `aud` claim contains `$audience` |

Remove entries to skip individual checks:

```php
public array $validateClaims = [
    'SignedWith',
    'ValidAt',
    // IssuedBy, IdentifiedBy and PermittedFor are skipped
];
```

---

## Environment Variable Overrides

CodeIgniter maps `.env` keys to config properties using dot notation. The following keys are supported:

```ini
jwt.signer    = "base64-encoded-secret"
jwt.issuer    = "https://api.my-app.com"
jwt.audience  = "https://my-app.com"
jwt.identifier = "my-app-v2"
jwt.expiresAt = "+2 hours"
jwt.algorithm = "Lcobucci\\JWT\\Signer\\Hmac\\Sha256"
jwt.throwable = true
jwt.validate  = true
```

---

## Programmatic Override

You can override any setting at runtime by passing a configured `JWTConfig` instance to the constructor:

```php
use Daycry\JWT\JWT;
use Daycry\JWT\Config\JWT as JWTConfig;

$config            = config('JWT');   // load base config
$config->expiresAt = '+5 minutes';   // tighten expiry for a specific token
$config->uid       = (string) $userId;

$jwt   = new JWT($config);
$token = $jwt->encode($payload);
```
