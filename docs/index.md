---
hide:
  - navigation
  - toc
---

<div class="hero" markdown>

# Daycry JWT

JWT (JSON Web Token) for **CodeIgniter 4**, built on [`lcobucci/jwt 5`](https://github.com/lcobucci/jwt) — **HMAC, RSA and ECDSA**, an immutable façade, and **key rotation**. Secure by default: it fails loudly instead of signing with a weak or missing key.

[Get started :material-rocket-launch:](getting-started.md){ .md-button .md-button--primary }
[Key rotation :material-autorenew:](advanced.md#key-rotation-with-kid){ .md-button }
[GitHub :material-github:](https://github.com/daycry/jwt){ .md-button }

</div>

## Features

<div class="grid cards" markdown>

-   :material-key-variant:{ .lg .middle } __HMAC, RSA & ECDSA__

    ---

    One config toggle (`algorithmType`) switches between symmetric HMAC and asymmetric RSA/ECDSA. The signer and `algorithmType` must agree — a mismatch fails fast with a clear message, not a cryptic key error.

    [:octicons-arrow-right-24: Configuration](configuration.md)

-   :material-cube-outline:{ .lg .middle } __Immutable façade__

    ---

    Every `with*()` customiser returns a **new instance** — `withSplitData`, `withExpiresAt`, `withLeeway`, `withIssuer`, `withAudience`, `withIdentifier`, `withHeader`, `withClaims` — so the shared config is never mutated across requests.

    [:octicons-arrow-right-24: Usage](usage.md)

-   :material-autorenew:{ .lg .middle } __Key rotation (`kid`)__

    ---

    Stamp a `kid` header and verify against a per-`kid` key map, so you can roll keys with **zero downtime**. An attacker-chosen `kid` can never downgrade the verifier.

    [:octicons-arrow-right-24: Key rotation](advanced.md#key-rotation-with-kid)

-   :material-shield-check:{ .lg .middle } __Fail-closed by default__

    ---

    Missing `signer`/`issuer`/`audience`/`identifier` throws (`null` **and** `""` rejected). `decode()` refuses to skip signature verification, and `jwt:key` enforces a 256-bit minimum.

    [:octicons-arrow-right-24: Threat model](threat-model.md)

-   :material-text-search:{ .lg .middle } __Validated reads__

    ---

    `decode()`, `getPayload()`, `getClaims()` and `getClaim()` all validate first. The parse-only helpers (`isExpired`, `extractClaimsUnsafe`) are clearly marked and log a warning so misuse is visible.

    [:octicons-arrow-right-24: Advanced](advanced.md)

-   :material-console:{ .lg .middle } __Spark commands__

    ---

    `jwt:key` (HMAC secret to `.env`), `jwt:keypair` (RSA/ECDSA PEM pair, curve-aware), and `jwt:publish` (config to your app) — auto-registered with Spark.

    [:octicons-arrow-right-24: CLI Commands](commands.md)

</div>

## Quick start

=== ":material-package-down: Install"

    ```bash
    composer require daycry/jwt
    php spark jwt:publish     # write app/Config/JWT.php
    php spark jwt:key         # generate jwt.signer in .env (>= 32 bytes)
    ```

=== ":material-lock: Encode"

    ```php
    use Daycry\JWT\JWT;

    $jwt = JWT::for();                 // pulls config('JWT')

    // The uid may be a string or an integer ID (e.g. a DB primary key).
    $token = $jwt->encode(['user_id' => 42, 'role' => 'admin'], 'user-42');
    ```

=== ":material-lock-open-check: Decode"

    ```php
    // Throws on failure…
    $claims = $jwt->decode($token);                 // Lcobucci\JWT\Token\Plain
    echo $claims->claims()->get('uid');             // "user-42"

    // …or get the original payload back (auto-decodes compact JSON):
    $payload = $jwt->getPayload($token);            // ['user_id' => 42, 'role' => 'admin']

    // …or the non-throwing flow:
    if ($jwt->tryDecode($maybeBad) === null) {
        return $this->response->setStatusCode(401);
    }
    ```

[:octicons-arrow-right-24: Full getting-started guide](getting-started.md)

## Security, by default

<div class="grid cards" markdown>

-   :material-signature-freehand:{ .lg .middle } __Signature always verified__

    ---

    If `validate = true` but `validateClaims` omits `SignedWith`, `decode()` throws instead of silently skipping verification. Tampered tokens (signature, header or payload) are rejected.

-   :material-clock-check:{ .lg .middle } __No stale clocks__

    ---

    The time constraints (`LooseValidAt` / `StrictValidAt`) are rebuilt per call and always use the current clock — never a frozen one. Only the stateless signer/key configuration is memoized.

    [:octicons-arrow-right-24: Configuration](configuration.md)

-   :material-test-tube:{ .lg .middle } __Tested & analysed__

    ---

    A focused PHPUnit suite (expiry, leeway, `nbf`, tampering, encrypted keys, key rotation) plus PHPStan level 8, Psalm and Rector keep the library correct and clean.

    [:octicons-arrow-right-24: Testing](testing.md)

</div>

---

<p class="md-content-footer" markdown>
**Resources** —
[GitHub](https://github.com/daycry/jwt) ·
[Packagist](https://packagist.org/packages/daycry/jwt) ·
[Issues](https://github.com/daycry/jwt/issues) ·
[Migration v2 → v3](migration-v2-to-v3.md) ·
[CodeIgniter 4](https://codeigniter4.github.io/)
</p>
