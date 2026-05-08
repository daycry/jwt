# Security Policy

## Supported Versions

| Version | Supported |
|---------|-----------|
| 1.x     | ✅        |
| < 1.0   | ❌        |

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
- Issues in the bundled CLI commands (`jwt:key`, `jwt:publish`) that lead to credential exposure or arbitrary file writes.
- Insecure defaults in `Daycry\JWT\Config\JWT`.

Out of scope:

- Vulnerabilities in `lcobucci/jwt` itself — please report those upstream.
- Vulnerabilities in CodeIgniter 4 itself — please report those upstream.
- Misconfiguration in user applications (using a weak signing key, disabling validation, etc.).

## Hardening Checklist

When integrating this library:

1. Always run `php spark jwt:key` (or generate a key with at least 32 bytes of entropy) before deploying.
2. Never commit `.env` files containing `jwt.signer`.
3. Keep `$validate = true` and `$throwable = true` in production.
4. Set short `$expiresAt` for access tokens and use refresh-token flows for longer sessions.
5. Rotate the signing key if it is ever exposed — every outstanding token immediately becomes invalid.
