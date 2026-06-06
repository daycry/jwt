# JWT for CodeIgniter 4

A JWT (JSON Web Token) library for CodeIgniter 4, built on top of [`lcobucci/jwt ^5`](https://github.com/lcobucci/jwt). Supports HMAC, RSA and ECDSA, an immutable façade, and key rotation.

> 📖 **Full documentation:** **<https://daycry.github.io/jwt/>** — this README is a quick overview; the site has the complete, searchable reference.

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
[![Docs](https://github.com/daycry/jwt/actions/workflows/docs.yml/badge.svg)](https://daycry.github.io/jwt/)
[![Coverage Status](https://coveralls.io/repos/github/daycry/jwt/badge.svg?branch=master)](https://coveralls.io/github/daycry/jwt?branch=master)

### Community
[![GitHub stars](https://img.shields.io/github/stars/daycry/jwt?style=social)](https://github.com/daycry/jwt/stargazers)
[![Donate](https://img.shields.io/badge/Donate-PayPal-blue.svg)](https://www.paypal.com/donate?business=SYC5XDT23UZ5G&no_recurring=0&item_name=Thank+you%21&currency_code=EUR)

---

## Requirements

- PHP **8.2** or higher
- CodeIgniter **4.x**
- `lcobucci/jwt ^5.5`

> Upgrading from v2.x? Read the [v2 → v3 migration guide](docs/migration-v2-to-v3.md).

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

$jwt = JWT::for();                 // pulls config('JWT')
// or inject an explicit config: new JWT(config('JWT'));

// Encode — the uid may be a string or an integer ID (e.g. a DB primary key)
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

> The library throws `JWTConfigurationException` if `jwt.signer`, `jwt.issuer`, `jwt.audience`, or `jwt.identifier` is missing — both `null` and an empty string `""` are rejected. Defaults are intentionally `null` to fail loudly.

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

### Short-lived tokens (`withExpiresAt`)

Override the configured `expiresAt` modifier for a single instance, without mutating the shared config — useful for short-lived access tokens. Like every `with*()` method it returns a new instance. Passing an empty string throws `InvalidArgumentException`.

```php
$accessToken = JWT::for()->withExpiresAt('+5 minutes')->encode($data);
```

### Clock skew tolerance (`LooseValidAt`)

```php
$jwt = JWT::for()->withLeeway(30);   // accept up to ±30s of skew
$jwt = JWT::for()->withLeeway(null); // reset to no leeway
```

`withLeeway()` accepts `null` to reset to "no leeway"; a negative value throws `InvalidArgumentException`.

### Per-instance customisers (3.2.0)

Every customiser returns a new instance, leaving the shared config untouched. Override claims, audiences, headers and extra claims per call:

```php
$jwt = JWT::for()
    ->withIssuer('https://api.my-app.com')
    ->withAudience('https://app-a.com', 'https://app-b.com') // multiple audiences
    ->withIdentifier(bin2hex(random_bytes(16)))              // unique jti
    ->withClaims(['scope' => 'admin'])                        // extra top-level claims
    ->withHeader('x-trace', $traceId);                        // custom JOSE header

$claims = $jwt->getClaims($token);            // validated array of all claims
$scope  = $jwt->getClaim($token, 'scope');    // validated single claim
```

### Key rotation with `kid` (3.2.0)

Tag issued tokens with a `kid` header and verify against a per-`kid` key map, so you can roll keys without invalidating tokens still in flight:

```php
// Issuing side — stamp the active key id.
$token = JWT::for()->withKeyId('2026-06')->encode($data);

// Verifying side (app/Config/JWT.php) — accept old and new keys during the window.
public ?string $keyId        = '2026-06';
public array   $verifyingKeys = [
    '2026-05' => '/path/old-public.pem',
    '2026-06' => '/path/new-public.pem',
];
```

On decode, the token's `kid` selects the matching key from `$verifyingKeys` (falling back to `$verifyingKey` / `$signer`). The configured signer/algorithm is always used, so a token's `kid` can never downgrade the verifier.

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

### Fail-closed configuration guards

The library refuses unsafe configurations up front instead of silently producing weak tokens. `JWTConfigurationException` is thrown when:

- `jwt.validateClaims` does **not** contain `'SignedWith'` while `jwt.validate = true` — `decode()` will not skip signature verification. To decode without any validation, set `jwt.validate = false` (intended for tests/debug only; `decode()` then logs a `warning`).
- `jwt.algorithmType` and `jwt.algorithm` disagree — `'symmetric'` requires an `Lcobucci\JWT\Signer\Hmac\*` signer, `'asymmetric'` requires `Rsa\*` or `Ecdsa\*`. (E.g. leaving the default HMAC `Sha256` on an `'asymmetric'` type is caught with a clear message instead of a cryptic key error.)

An invalid `jwt.canOnlyBeUsedAfter` or `jwt.expiresAt` modifier (anything `DateTimeImmutable::modify()` rejects) throws `InvalidArgumentException` consistently across PHP versions.

---

## Utility Methods

| Method | Returns | Description |
|---|---|---|
| `decode(string $token)` | `Plain` | Validates and returns the parsed token. Throws on failure. |
| `tryDecode(string $token)` | `?Plain` | Like `decode()` but returns `null` on a **token** failure. A `JWTConfigurationException` (misconfiguration) still propagates. |
| `getPayload(string $token)` | `mixed` | Validates + returns the original payload (auto-decoded for compact mode). |
| `getClaims(string $token)` | `array` | Validated array of all claims (the safe counterpart of `extractClaimsUnsafe()`). |
| `getClaim(string $token, string $name)` | `mixed` | Validated single claim value (`null` when absent). |
| `isValid(string $token)` | `bool` | True iff `tryDecode()` succeeds. |
| `isExpired(string $token)` | `bool` | True for malformed/expired tokens. **Parses without verifying the signature** — never gate access on it. |
| `getTimeToExpiry(string $token)` | `?int` | Seconds until `exp`, or `null`. **Does not verify the signature.** |
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

> On Windows, `jwt:keypair` warns that `chmod()` cannot enforce file permissions — restrict the private key with NTFS ACLs (e.g. `icacls`) instead. It also warns when `--passphrase` is passed on the command line, because that value can leak via the process list and shell history; prefer a secrets manager or interactive entry.

---

## Security Best Practices

1. **Use a strong key** — `php spark jwt:key` enforces a 32-byte (256-bit) minimum, the floor for HS256.
2. **Set short expiry times** for API access tokens (`withExpiresAt('+15 minutes')`).
3. **Enable all validation constraints** in production (keep `'SignedWith'` in `jwt.validateClaims`).
4. **Never commit** `.env` or any file containing `jwt.signer` / private keys.
5. **Rotate keys without downtime** using the `kid` header and `jwt.verifyingKeys` map (see [Key rotation](#key-rotation-with-kid-320)) — keep the old key in the map until its tokens have expired, then drop it. If a key is *leaked*, remove it from the map immediately to revoke its tokens.

---

## Testing

```bash
composer test
# or without coverage (faster)
vendor/bin/phpunit --no-coverage
```

---

## Documentation

📖 **The full, searchable documentation is published at <https://daycry.github.io/jwt/>** (built with MkDocs Material from the [`docs/`](docs/) folder).

| Document | Description |
|---|---|
| [Getting Started](https://daycry.github.io/jwt/getting-started/) | Installation and first token in minutes |
| [Configuration](https://daycry.github.io/jwt/configuration/) | Every property, its type, default, and `.env` key |
| [Usage](https://daycry.github.io/jwt/usage/) | Complete API reference with examples |
| [Advanced](https://daycry.github.io/jwt/advanced/) | Utility methods, key rotation, middleware, multi-tenant patterns |
| [CLI Commands](https://daycry.github.io/jwt/commands/) | `jwt:key`, `jwt:keypair`, `jwt:publish` reference |
| [Threat Model](https://daycry.github.io/jwt/threat-model/) | Security model and guarantees |
| [Testing](https://daycry.github.io/jwt/testing/) | Test suite structure and writing new tests |
| [Migration v2 → v3](https://daycry.github.io/jwt/migration-v2-to-v3/) | Upgrade guide |

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
