# JWT for CodeIgniter 4

A JWT (JSON Web Token) library for CodeIgniter 4, built on top of [`lcobucci/jwt ^5`](https://github.com/lcobucci/jwt). Supports HMAC, RSA and ECDSA.

### Package
[![Latest Stable Version](https://img.shields.io/packagist/v/daycry/jwt.svg?label=stable)](https://packagist.org/packages/daycry/jwt)
[![Total Downloads](https://img.shields.io/packagist/dt/daycry/jwt.svg)](https://packagist.org/packages/daycry/jwt)
[![Monthly Downloads](https://img.shields.io/packagist/dm/daycry/jwt.svg)](https://packagist.org/packages/daycry/jwt)
[![PHP Version Require](https://img.shields.io/packagist/dependency-v/daycry/jwt/php?color=8892bf)](https://packagist.org/packages/daycry/jwt)
[![License](https://img.shields.io/github/license/daycry/jwt)](https://github.com/daycry/jwt/blob/master/LICENSE)

### Quality
[![PHP Tests](https://github.com/daycry/jwt/actions/workflows/phpunit.yml/badge.svg)](https://github.com/daycry/jwt/actions/workflows/phpunit.yml)
[![PHPStan](https://github.com/daycry/jwt/actions/workflows/phpstan.yml/badge.svg)](https://github.com/daycry/jwt/actions/workflows/phpstan.yml)
[![Psalm](https://github.com/daycry/jwt/actions/workflows/psalm.yml/badge.svg)](https://github.com/daycry/jwt/actions/workflows/psalm.yml)
[![Rector](https://github.com/daycry/jwt/actions/workflows/rector.yml/badge.svg)](https://github.com/daycry/jwt/actions/workflows/rector.yml)
[![Code Style](https://github.com/daycry/jwt/actions/workflows/code-style.yml/badge.svg)](https://github.com/daycry/jwt/actions/workflows/code-style.yml)
[![CodeQL](https://github.com/daycry/jwt/actions/workflows/codeql.yml/badge.svg)](https://github.com/daycry/jwt/actions/workflows/codeql.yml)
[![Coverage Status](https://coveralls.io/repos/github/daycry/jwt/badge.svg?branch=master)](https://coveralls.io/github/daycry/jwt?branch=master)

### Community
[![GitHub stars](https://img.shields.io/github/stars/daycry/jwt?style=social)](https://github.com/daycry/jwt/stargazers)
[![Donate](https://img.shields.io/badge/Donate-PayPal-blue.svg)](https://www.paypal.com/donate?business=SYC5XDT23UZ5G&no_recurring=0&item_name=Thank+you%21&currency_code=EUR)

---

## Requirements

- PHP **8.2** or higher
- CodeIgniter **4.x**
- `lcobucci/jwt ^5.5`

> Upgrading from v1.x? Read the [v1 → v2 migration guide](docs/migration-v1-to-v2.md).

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

```bash
php spark jwt:publish     # write app/Config/JWT.php
php spark jwt:key         # generate jwt.signer in .env
```

```php
use Daycry\JWT\JWT;

$jwt = JWT::for();   // pulls config('JWT')

// Encode
$token = $jwt->encode(['user_id' => 42, 'role' => 'admin'], 'user-42');

// Decode + validate (throws on failure)
$claims = $jwt->decode($token);                  // Plain
echo $claims->claims()->get('uid');              // "user-42"

// Symmetric helper — get the original payload back
$payload = $jwt->getPayload($token);             // ['user_id' => 42, 'role' => 'admin']

// Non-throwing alternative
$claims = $jwt->tryDecode($maybeBadToken);
if ($claims === null) {
    return $this->response->setStatusCode(401);
}
```

> The library throws `JWTConfigurationException` if `jwt.signer`, `jwt.issuer`, `jwt.audience`, or `jwt.identifier` is empty. Defaults are intentionally `null` to fail loudly.

---

## Configuration

After publishing, edit `app/Config/JWT.php`. All properties are inherited from `Daycry\JWT\Config\JWT` and overridable via `.env`.

### HMAC (default)

```ini
jwt.algorithmType = "symmetric"
jwt.signer        = "<base64-secret-from-jwt:key>"
jwt.issuer        = "https://api.my-app.com"
jwt.audience      = "https://my-app.com"
jwt.identifier    = "my-app-v2"
jwt.expiresAt     = "+1 hour"
jwt.leeway        = "30"
```

### RSA / ECDSA

```bash
php spark jwt:keypair --algorithm=rsa --bits=2048 --output=writable/keys
```

```ini
jwt.algorithmType = "asymmetric"
jwt.signingKey    = "/var/www/app/writable/keys/jwt-private.pem"
jwt.verifyingKey  = "/var/www/app/writable/keys/jwt-public.pem"
jwt.issuer        = "https://api.my-app.com"
jwt.audience      = "https://my-app.com"
jwt.identifier    = "my-app-v2"
```

In `app/Config/JWT.php` set the signer class:

```php
public string $algorithm = \Lcobucci\JWT\Signer\Rsa\Sha256::class;   // RS256
// or \Lcobucci\JWT\Signer\Ecdsa\Sha256::class for ES256
```

See [docs/configuration.md](docs/configuration.md) for the full reference.

---

## Usage

### Compact array payload (default)

```php
$token = $jwt->encode(['user_id' => 1, 'role' => 'admin']);

$payload = $jwt->getPayload($token);  // ['user_id' => 1, 'role' => 'admin']
```

### Split mode — claims at the top level

```php
$jwt   = JWT::for()->withSplitData();
$token = $jwt->encode(['user_id' => 1, 'role' => 'admin']);
$claims = $jwt->decode($token);

echo $claims->claims()->get('role');  // "admin"
```

### Custom payload claim name

```php
$jwt = JWT::for()->withParamData('payload');
$jwt->getPayload($jwt->encode('hello'));  // "hello"
```

### Clock skew tolerance (`LooseValidAt`)

```php
$jwt = JWT::for()->withLeeway(30); // accept up to ±30s of skew
```

---

## Error Handling

```php
use Daycry\JWT\Exceptions\InvalidTokenException;
use Lcobucci\JWT\Validation\RequiredConstraintsViolated;

try {
    $claims = $jwt->decode($token);
} catch (RequiredConstraintsViolated $e) {
    // Signature, issuer, audience, exp, etc.
    return $this->response->setStatusCode(401)->setJSON(['error' => $e->getMessage()]);
} catch (InvalidTokenException $e) {
    // Malformed or non-Plain token.
    return $this->response->setStatusCode(400)->setJSON(['error' => 'Bad token']);
}
```

For a non-throwing flow:

```php
$claims = $jwt->tryDecode($token);
if ($claims === null) {
    return $this->response->setStatusCode(401);
}
```

---

## Utility Methods

| Method | Returns | Description |
|---|---|---|
| `decode(string $token)` | `Plain` | Validates and returns the parsed token. Throws on failure. |
| `tryDecode(string $token)` | `?Plain` | Same as `decode()` but returns `null` on failure. |
| `getPayload(string $token)` | `mixed` | Validates + returns the original payload (auto-decoded for compact mode). |
| `isValid(string $token)` | `bool` | True iff `tryDecode()` succeeds. |
| `isExpired(string $token)` | `bool` | True for malformed tokens or tokens past `exp`. |
| `getTimeToExpiry(string $token)` | `?int` | Seconds until `exp`, or `null`. |
| `extractClaimsUnsafe(string $token)` | `?array` | Claims **without validation**. Logs a warning unless `Config::$allowUnsafeExtraction = true`. |

---

## CLI Commands

```bash
# Publish config to app/Config/JWT.php
php spark jwt:publish

# Generate an HMAC key (default 32 bytes) and write to .env
php spark jwt:key
php spark jwt:key 64 --show
php spark jwt:key --force

# Generate an asymmetric key pair
php spark jwt:keypair --algorithm=rsa   --bits=2048
php spark jwt:keypair --algorithm=ecdsa --curve=prime256v1 --output=writable/keys
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
