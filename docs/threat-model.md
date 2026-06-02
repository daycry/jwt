# Threat Model

> **[← Back to index](index.md)**

This document describes what `daycry/jwt` defends against, what it explicitly does **not** defend against, and the assumptions you must satisfy in your application code for the protections to hold.

---

## What the library protects against

When `decode()` succeeds with the default `validateClaims`, the library has confirmed that:

1. **Integrity / authenticity** — the token's signature was produced with the same key/algorithm pair that the verifier is configured with. A tampered claims body, a tampered header (including `alg` swapping), or any unauthorised mutation rejects validation. `SignedWith` is part of the default `$validateClaims`, and `decode()` **refuses to run without it**: if `$validate = true` but `SignedWith` is missing from `$validateClaims`, `JWTConfigurationException` is thrown rather than silently skipping signature verification. (`tryDecode()` is the non-throwing wrapper — it returns `null` instead of throwing, but applies the exact same constraint set.)
2. **Issuer pinning (`iss`)** — the token's `iss` matches `Config\JWT::$issuer`. Tokens from a different issuer (even if signed with the same key) are rejected.
3. **Audience pinning (`aud`)** — the token lists `Config\JWT::$audience` among its audiences. Tokens minted for a different service are rejected.
4. **Application identifier (`jti`)** — the token's `jti` matches `Config\JWT::$identifier`.
5. **Temporal validity** — `iat` is not in the future, `nbf` has passed and `exp` has not, all subject to the configured `leeway` and your choice of `LooseValidAt` (default, also accepted under the legacy alias `ValidAt`) or `StrictValidAt`.

Configuration mistakes that would weaken these guarantees fail loudly: `JWTConfigurationException` is thrown the moment you try to encode or decode without a configured `signer` / `signingKey` / `verifyingKey` / `issuer` / `audience` / `identifier`. An **empty string** counts as "not configured" for `issuer` / `audience` / `identifier`, exactly like `null` — you cannot weaken a claim check by blanking it. A mismatch between `$algorithmType` and the `$algorithm` signer class (e.g. `asymmetric` left on the default HMAC signer, or `symmetric` paired with an RSA/ECDSA signer) is also caught up front with a descriptive `JWTConfigurationException` instead of a cryptic lower-level key error.

---

## What the library does **not** protect against

These threats live outside the JWT specification. The library cannot mitigate them on its own — your application must.

### Token theft / leakage
- Storing tokens in `localStorage` exposed to XSS.
- Logging full bearer tokens in access logs, error messages, or analytics.
- Sending tokens over plain HTTP. Always use HTTPS.

### Replay attacks
- The library has **no state**. A captured token remains valid until `exp`. If your threat model requires single-use semantics:
  - Issue short-lived access tokens (`+5 minutes`) and use a refresh-token flow.
  - Track used `jti` values in a server-side store (Redis, DB) and reject duplicates.
  - Bind tokens to a transport — e.g. a TLS channel binding or DPoP-style proof-of-possession header.

### Token revocation
- Once issued, a JWT is valid until `exp`. There is no built-in "log out everywhere" primitive. Implement either:
  - Short `exp` plus refresh-token rotation.
  - A server-side allow/deny list keyed by `jti` or `uid`.
  - Key rotation (rotate `jwt.signer` / `signingKey` to invalidate **all** outstanding tokens at once — heavy but effective).

### Privacy of payload
- JWTs are **signed, not encrypted**. Anyone holding the token can read every claim. Do not store secrets, PII you would not log, or anything subject to regulation in the payload. If you need encryption, use JWE — outside this library's scope.

### Side-channel attacks against the host
- The library uses `lcobucci/jwt` which uses `hash_hmac()` for HMAC and OpenSSL bindings for RSA/ECDSA. Both rely on PHP's underlying implementations being constant-time. Side-channel attacks against those are out of scope here; consult the upstream projects.

### Compromised signing key
- If `jwt.signer` (HMAC) or the private key (RSA / ECDSA) leaks, an attacker can mint arbitrary tokens. The library cannot detect this — only key rotation can recover.

### Algorithm confusion (`alg=none`, RS256↔HS256 swap)
- `lcobucci/jwt 5` rejects `alg=none` tokens and refuses to verify a token if its `alg` does not match the expected family. The library inherits these protections. On top of that, the library validates your own configuration: if `$algorithmType` and the `$algorithm` signer class disagree — `symmetric` requires an `Lcobucci\JWT\Signer\Hmac\*` signer, `asymmetric` requires an `Rsa\*` or `Ecdsa\*` signer — `buildConfiguration()` throws `JWTConfigurationException` before any token is parsed. This closes the door on a misconfigured verifier (e.g. an asymmetric setup accidentally left on the default HMAC `Sha256`) that an attacker could otherwise exploit for an RS256↔HS256 swap. **However**, if your verifier accepts tokens from multiple issuers/algorithms simultaneously, you are responsible for picking the correct verifier per issuer (`extractClaimsUnsafe()` is the inspection-before-verify hook for this case).

### Denial of service via large tokens
- The library does not enforce a maximum token size. A request body of several megabytes will be parsed before validation can reject it. If exposed to untrusted clients, gate the input upstream (e.g. a max body size on your reverse proxy).

---

## Operational hardening checklist

| # | Action |
|---|---|
| 1 | Generate keys with `php spark jwt:key` (HMAC) or `php spark jwt:keypair` (RSA/ECDSA). Never commit them. |
| 2 | Rotate the signing key at least every 12 months, and immediately if you suspect compromise. |
| 3 | Keep `Config\JWT::$validate = true` and `SignedWith` in `$validateClaims` in production. Setting `$validate = false` skips **all** validation and logs a `warning` via `log_message()` on every `decode()` — it exists for tests/debug only, never for production. |
| 4 | Set `$expiresAt` as short as your UX allows (`+15 minutes` for access tokens is a common baseline). For one-off short-lived tokens, prefer `JWT::for()->withExpiresAt('+5 minutes')->encode(...)` — a per-instance override that never mutates the shared config. |
| 5 | Use a distinct `$identifier` per environment (dev, staging, prod) so dev tokens never validate against prod. |
| 6 | Serve the API exclusively over HTTPS. Enable HSTS where possible. |
| 7 | Strip `Authorization` headers from access logs and APM payloads. |
| 8 | Set `$allowUnsafeExtraction = true` only in code paths that genuinely need claim inspection without verification. Audit those paths. |
| 9 | When using asymmetric keys, store the private key with mode `0600` and outside the document root. On Windows, `chmod()` cannot enforce permissions (`jwt:keypair` warns about this) — restrict the file with NTFS ACLs (e.g. `icacls`) instead. Avoid passing `--passphrase` on the command line, where it leaks into the process list and shell history; the command warns when you do. Prefer secrets managers (Vault, AWS Secrets Manager, GCP Secret Manager) over committed config. |
| 10 | If you accept tokens from external partners, configure a **separate** `JWT` instance per issuer with that issuer's public key — never share verification keys across trust domains. |

---

## Reporting issues

Security issues should not be filed as public GitHub issues. Follow [SECURITY.md](../SECURITY.md) for the private-disclosure path.
