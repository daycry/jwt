# Security Policy

## Supported Versions

| Version | Supported |
|---------|-----------|
| 3.x     | ✅        |
| 2.x     | ❌ (EOL — upgrade to 3.x) |
| < 2.0   | ❌ (EOL — upgrade to 3.x) |

## Reporting a Vulnerability

**Do not open a public GitHub issue for security reports.**

If you believe you have found a vulnerability in `daycry/jwt`, please report it privately so it can be triaged before disclosure:

1. Email the maintainer directly via the contact details listed on the [maintainer's GitHub profile](https://github.com/daycry).
2. Alternatively, use [GitHub's private security advisory](https://github.com/daycry/jwt/security/advisories/new).

Please include:

- A clear description of the issue and its impact.
- Steps to reproduce, ideally with a minimal proof-of-concept.
- The version(s) affected and your environment (PHP version, CodeIgniter version, `lcobucci/jwt` version).

You can expect an initial acknowledgement within 5 business days. Coordinated disclosure timelines depend on severity and complexity, but the maintainer aims to release a fix within 30 days for critical issues.

## Scope

The following are in scope:

- Token forgery, signature bypass, or algorithm confusion in `Daycry\JWT\JWT`.
- Issues in the bundled CLI commands (`jwt:key`, `jwt:keypair`, `jwt:publish`) that lead to credential exposure or arbitrary file writes.
- Insecure defaults in `Daycry\JWT\Config\JWT`.

Out of scope:

- Vulnerabilities in `lcobucci/jwt` itself — please report those upstream.
- Vulnerabilities in CodeIgniter 4 itself — please report those upstream.
- Misconfiguration in user applications (using a weak signing key, disabling validation, etc.).

## Hardening Checklist

When integrating this library:

1. Always run `php spark jwt:key` (or generate a key with at least 32 bytes of entropy) before deploying.
2. Never commit `.env` files containing `jwt.signer`, nor the private key produced by `jwt:keypair`.
3. Keep `$validate = true` in production. When you need a non-throwing decode, use `JWT::tryDecode()` (returns `?Plain`); reserve the throwing `JWT::decode()` for paths that handle the exception.
4. Keep `'SignedWith'` in `$validateClaims`. The library refuses to silently skip signature verification: if `$validate = true` but `'SignedWith'` is absent, `decode()` throws `JWTConfigurationException`. Set `$validate = false` only to bypass validation in tests/debug.
5. Set short `$expiresAt` for access tokens and use refresh-token flows for longer sessions. Use `JWT::for()->withExpiresAt('+5 minutes')->encode($data)` for per-instance short-lived tokens without mutating the shared config.
6. Rotate the signing key if it is ever exposed — every outstanding token immediately becomes invalid.

## Built-in Hardening Guards

The library fails closed rather than producing a token or accepting one under an unsafe configuration:

- **Mandatory signature check.** `decode()` requires `'SignedWith'` in `$validateClaims` while `$validate = true` and throws `JWTConfigurationException` otherwise, so a misconfiguration can never quietly accept unsigned/forged tokens.
- **Validation-disabled warning.** `decode()` with `$validate = false` logs a `warning` via `log_message()` (the same way `extractClaimsUnsafe()` does). It is intended for tests/debug only; the log entry surfaces any accidental production use.
- **Algorithm-confusion guard.** A mismatch between `$algorithmType` and the `$algorithm` signer class throws `JWTConfigurationException` (e.g. `'asymmetric'` left on the default HMAC signer, or `'symmetric'` paired with an RSA/ECDSA signer). Symmetric requires an `Lcobucci\JWT\Signer\Hmac\*` signer; asymmetric requires `Rsa\*` or `Ecdsa\*`.

## Key-pair Generation (`jwt:keypair`)

- On Windows `chmod()` cannot enforce file permissions, so the command warns you to restrict the private key with NTFS ACLs (e.g. `icacls`) so only the service account can read it.
- Passing `--passphrase` on the command line is warned against, because the value can leak via the process list and shell history. Prefer a secrets manager or interactive entry.
