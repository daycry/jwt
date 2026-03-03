[![Donate](https://img.shields.io/badge/Donate-PayPal-green.svg)](https://www.paypal.com/donate?business=SYC5XDT23UZ5G&no_recurring=0&item_name=Thank+you%21&currency_code=EUR)

# JWT for CodeIgniter 4

A JWT (JSON Web Token) library for CodeIgniter 4, built on top of [`lcobucci/jwt ^4`](https://github.com/lcobucci/jwt).

[![Build Status](https://github.com/daycry/jwt/workflows/PHP%20Tests/badge.svg)](https://github.com/daycry/jwt/actions?query=workflow%3A%22PHP+Tests%22)
[![Coverage Status](https://coveralls.io/repos/github/daycry/jwt/badge.svg?branch=master)](https://coveralls.io/github/daycry/jwt?branch=master)
[![Downloads](https://poser.pugx.org/daycry/jwt/downloads)](https://packagist.org/packages/daycry/jwt)
[![GitHub release (latest by date)](https://img.shields.io/github/v/release/daycry/jwt)](https://packagist.org/packages/daycry/jwt)
[![GitHub stars](https://img.shields.io/github/stars/daycry/jwt)](https://packagist.org/packages/daycry/jwt)
[![GitHub license](https://img.shields.io/github/license/daycry/jwt)](https://github.com/daycry/jwt/blob/master/LICENSE)

---

## Requirements

- PHP **8.1** or higher
- CodeIgniter **4.x**
- `lcobucci/jwt ^4.0`

---

## Installation

```bash
composer require daycry/jwt
```

### Publish the configuration file

```bash
php spark jwt:publish
```

### Generate a signing key

```bash
php spark jwt:key
```

The key is written automatically to `.env` as `jwt.signer`. Use `--show` to print it without touching the file.

> ⚠️ Never commit `.env` to version control.

---

## Quick Start

```php
use Daycry\JWT\JWT;

$jwt = new JWT();

// Encode
$token = $jwt->encode(['user_id' => 42, 'role' => 'admin'], 'user-42');

// Decode & validate
$claims = $jwt->decode($token);

echo $claims->get('data'); // '{"user_id":42,"role":"admin"}'  (compact mode)
echo $claims->get('uid');  // "user-42"
```

---

## Configuration

After publishing, edit `app/Config/JWT.php`:

```php
<?php

namespace Config;

use Daycry\JWT\Config\JWT as BaseJWT;

class JWT extends BaseJWT
{
    // Override only what you need — all properties are inherited from BaseJWT.
}
```

Key properties (all overridable via `.env`):

| Property | Default | Description |
|---|---|---|
| `$signer` | *(base64 string)* | Symmetric signing key |
| `$algorithm` | `Sha256::class` | HMAC algorithm (`Sha256`, `Sha384`, `Sha512`) |
| `$issuer` | `http://example.local` | `iss` claim |
| `$audience` | `http://example.local` | `aud` claim |
| `$identifier` | `4f1g23a12aa` | `jti` claim |
| `$expiresAt` | `+24 hour` | Token lifetime (`DateTimeImmutable::modify` string) |
| `$canOnlyBeUsedAfter` | `+0 minute` | `nbf` offset |
| `$validate` | `true` | Run validation constraints on `decode()` |
| `$throwable` | `true` | Throw on validation failure (vs. return the exception) |
| `$validateClaims` | `[SignedWith, IssuedBy, ValidAt, IdentifiedBy, PermittedFor]` | Active constraints |

`.env` example:

```ini
jwt.signer     = "your-base64-encoded-secret"
jwt.issuer     = "https://api.my-app.com"
jwt.audience   = "https://my-app.com"
jwt.identifier = "my-app-v2"
jwt.expiresAt  = "+1 hour"
```

---

## Usage

### Scalar payload

```php
$token  = $jwt->encode('hello');
$claims = $jwt->decode($token);

echo $claims->get('data'); // "hello"
```

### Array payload — compact (default)

The array is JSON-encoded into a single `data` claim:

```php
$token  = $jwt->encode(['user_id' => 1, 'role' => 'admin']);
$claims = $jwt->decode($token);

$payload = json_decode($claims->get('data'), true);
echo $payload['role']; // "admin"
```

### Array payload — split mode

Each key becomes its own top-level claim:

```php
$jwt->setSplitData();
$token  = $jwt->encode(['user_id' => 1, 'role' => 'admin']);
$claims = $jwt->decode($token);

echo $claims->get('role'); // "admin"
```

### Custom claim name

```php
$jwt->setParamData('payload');
$token  = $jwt->encode('hello');
$claims = $jwt->decode($token);

echo $claims->get('payload'); // "hello"
```

### Custom uid per token

```php
$token  = $jwt->encode($data, 'user-42');
$claims = $jwt->decode($token);

echo $claims->get('uid'); // "user-42"
```

---

## Error Handling

**Throw on failure (default, `$throwable = true`):**

```php
use Lcobucci\JWT\Validation\RequiredConstraintsViolated;

try {
    $claims = $jwt->decode($token);
} catch (RequiredConstraintsViolated $e) {
    return $this->response->setStatusCode(401)->setJSON(['error' => $e->getMessage()]);
}
```

**Return on failure (`$throwable = false`):**

```php
$config->throwable = false;
$result = (new JWT($config))->decode($token);

if ($result instanceof RequiredConstraintsViolated) {
    echo $result->getMessage();
} else {
    echo $result->get('data');
}
```

---

## Utility Methods

| Method | Returns | Description |
|---|---|---|
| `isValid(string $token)` | `bool` | Validates without decoding; never throws |
| `isExpired(string $token)` | `bool` | Checks `exp` claim only; no signature check |
| `getTimeToExpiry(string $token)` | `?int` | Seconds until expiry; `null` if no `exp` claim |
| `extractClaimsUnsafe(string $token)` | `?array` | All claims as array, **no validation** |
| `clearCache()` | `void` | Clears the internal constraint cache |

```php
if ($jwt->isExpired($token)) {
    // redirect to refresh flow
}

$ttl = $jwt->getTimeToExpiry($token);
if ($ttl !== null && $ttl < 300) {
    // warn client to refresh soon
}

// Inspect claims without verifying signature
$claims = $jwt->extractClaimsUnsafe($token);
$userId = $claims['uid'] ?? null;
```

> `extractClaimsUnsafe()` skips all validation. Only use it when the token has already been verified elsewhere, or for inspection purposes.

---

## CLI Commands

```bash
# Publish config to app/Config/JWT.php
php spark jwt:publish

# Generate a 32-byte key and write to .env
php spark jwt:key

# Generate a 64-byte key, display only
php spark jwt:key 64 --show

# Force overwrite existing key in .env
php spark jwt:key --force
```

---

## Security Best Practices

1. **Use a strong key** — at least 32 bytes (`php spark jwt:key`).
2. **Set short expiry times** for API access tokens (`+15 minutes`).
3. **Enable all validation constraints** in production.
4. **Never commit** `.env` or any file containing `jwt.signer`.
5. **Rotate the key** immediately if exposed — all outstanding tokens become invalid.

---

## Testing

```bash
composer test
# or without coverage (faster)
vendor/bin/phpunit --no-coverage
```

---

## Documentation

Full reference documentation is in the [`docs/`](docs/) folder:

| Document | Description |
|---|---|
| [Getting Started](docs/getting-started.md) | Installation and first token in minutes |
| [Configuration](docs/configuration.md) | Every property, its type, default, and `.env` key |
| [Usage](docs/usage.md) | Complete API reference with examples |
| [CLI Commands](docs/commands.md) | `jwt:key` and `jwt:publish` reference |
| [Advanced](docs/advanced.md) | Utility methods, middleware, multi-tenant patterns |
| [Testing](docs/testing.md) | Test suite structure and writing new tests |

---

## Contributing

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/my-feature`
3. Commit your changes: `git commit -m 'Add my feature'`
4. Push and open a Pull Request

---

## License

MIT — see [LICENSE](LICENSE).

---

## Support

- 🐛 [Open an issue](https://github.com/daycry/jwt/issues) for bug reports or feature requests
- 💰 [Donate via PayPal](https://www.paypal.com/donate?business=SYC5XDT23UZ5G&no_recurring=0&item_name=Thank+you%21&currency_code=EUR)
