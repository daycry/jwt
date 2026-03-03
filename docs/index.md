# daycry/jwt — Documentation

A high-performance JWT (JSON Web Token) library for **CodeIgniter 4**, built on top of [`lcobucci/jwt ^4`](https://github.com/lcobucci/jwt).

---

## Table of Contents

| Document | Description |
|---|---|
| [Getting Started](getting-started.md) | Installation, requirements, and a working example in minutes |
| [Configuration](configuration.md) | Every configuration option explained with defaults and examples |
| [Usage](usage.md) | Encoding, decoding, validation, and the full public API |
| [CLI Commands](commands.md) | `jwt:key` and `jwt:publish` — key generation and config publishing |
| [Advanced](advanced.md) | Utility methods, error handling, caching, and environment variables |
| [Testing](testing.md) | Running the test suite and understanding the test structure |

---

## Overview

```
composer require daycry/jwt
```

```php
use Daycry\JWT\JWT;

$jwt = new JWT();

// Encode
$token = $jwt->encode(['user_id' => 42, 'role' => 'admin'], 'user-42');

// Decode
$claims = $jwt->decode($token);
echo $claims->get('user_id'); // 42
```

---

## Architecture at a Glance

```
src/
├── JWT.php               ← Core library class
├── Config/
│   └── JWT.php           ← Configuration class (extends BaseConfig)
└── Commands/
    ├── JWTGenerateKey.php ← php spark jwt:key
    └── JWTPublish.php     ← php spark jwt:publish
```

The library uses two internal optimisations:

- **Lazy loading** — the `lcobucci/jwt` `Configuration` object is built only on first use, so constructing a `JWT` instance is cheap.
- **Constraint caching** — validation constraints are built once per unique `validateClaims` combination and reused on subsequent `decode()` calls.

---

## Requirements

| Dependency | Version |
|---|---|
| PHP | `^8.1` |
| CodeIgniter | `^4.0` |
| lcobucci/jwt | `^4.0` |

---

## License

MIT — see [LICENSE](../LICENSE).
