# daycry/jwt — Documentation

JWT (JSON Web Token) library for **CodeIgniter 4**, built on top of [`lcobucci/jwt ^5`](https://github.com/lcobucci/jwt). Supports HMAC, RSA and ECDSA.

---

## Table of Contents

| Document | Description |
|---|---|
| [Getting Started](getting-started.md) | Installation, requirements, first token in minutes |
| [Configuration](configuration.md) | Every option explained: HMAC and asymmetric, env-var convention |
| [Usage](usage.md) | Encoding, decoding, the immutable `with*()` API |
| [CLI Commands](commands.md) | `jwt:key`, `jwt:keypair`, `jwt:publish` |
| [Advanced](advanced.md) | `tryDecode`, `getPayload`, `extractClaimsUnsafe`, leeway, error handling |
| [Testing](testing.md) | Running the test suite |
| [Migration v2 → v3](migration-v2-to-v3.md) | Upgrade guide from `lcobucci/jwt 4`-based v2.x to the v3.x rewrite |

---

## Overview

```bash
composer require daycry/jwt
php spark jwt:publish
php spark jwt:key
```

```php
use Daycry\JWT\JWT;

$jwt = JWT::for();

$token  = $jwt->encode(['user_id' => 42, 'role' => 'admin'], 'user-42');
$claims = $jwt->decode($token);                  // Plain — throws on failure
$payload = $jwt->getPayload($token);              // ['user_id' => 42, 'role' => 'admin']
```

---

## Architecture

```
src/
├── JWT.php                           ← Immutable facade over lcobucci/jwt
├── Config/
│   └── JWT.php                       ← BaseConfig with HMAC + asymmetric fields
├── Commands/
│   ├── JWTGenerateKey.php            ← php spark jwt:key       (HMAC secret)
│   ├── JWTKeyPair.php                ← php spark jwt:keypair   (RSA / ECDSA pair)
│   └── JWTPublish.php                ← php spark jwt:publish
└── Exceptions/
    ├── JWTConfigurationException.php ← Missing/invalid config
    └── InvalidTokenException.php     ← Malformed token (parse stage)
```

Design notes:

- **Immutable instances.** `withSplitData()`, `withParamData()`, `withLeeway()`, `withExpiresAt()` return new instances — the original is never mutated.
- **Always-validate.** `decode()` always throws on parse or validation failure. `tryDecode()` returns `?Plain` for non-throwing flows.
- **Symmetric + asymmetric.** `Config\JWT::$algorithmType` toggles between HMAC (`signer`) and RSA/ECDSA (`signingKey` + `verifyingKey`).
- **No global state, no caches.** Validation constraints are rebuilt per call; `LooseValidAt` always uses the current clock instead of a frozen one.

---

## Requirements

| Dependency | Version |
|---|---|
| PHP | `^8.2` |
| CodeIgniter | `^4.0` |
| lcobucci/jwt | `^5.5` |

---

## License

MIT — see [LICENSE](../LICENSE).
