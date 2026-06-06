# Changelog

All notable changes to `daycry/jwt` are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project adheres to [Semantic Versioning](https://semver.org/).

## [3.2.0] — 2026-06-06

### Added
- **Key rotation (`kid`).** `Config\JWT::$keyId` writes a `kid` header on every issued token, and `Config\JWT::$verifyingKeys` (a `kid => key` map of PEM contents/paths or base64 secrets) selects the verification key by the token's `kid` on decode, falling back to the single `$verifyingKey` / `$signer`. The configured signer/algorithm is always used, so a token's `kid` can never downgrade the verifier. Per-instance override via `JWT::withKeyId()`.
- **New immutable customisers:** `withIssuer()`, `withAudience(string ...$aud)` (multi-audience on encode), `withIdentifier()`, `withHeader()` / `withHeaders()` (the internal `cty` header is protected), and `withClaims()` (reserved claim names rejected).
- **Validated reads:** `getClaims()` (the validated counterpart of `extractClaimsUnsafe()`) and `getClaim()`.
- `JWTConfigurationException::reservedClaimInSplitMode()`; `Daycry\JWT\Enums\AlgorithmType` and `Daycry\JWT\Enums\ConstraintName` enums.
- Published documentation site (MkDocs Material) at <https://daycry.github.io/jwt/>, built and deployed from `development` by `.github/workflows/docs.yml`.

### Changed
- `tryDecode()` / `isValid()` now only swallow **token** failures (`InvalidTokenException` / `RequiredConstraintsViolated`). A `JWTConfigurationException` (e.g. `validateClaims` missing `SignedWith`, or an unknown constraint) now propagates instead of masquerading as an invalid token — a misconfigured deployment no longer makes every valid token silently look invalid.
- `isExpired()` / `getTimeToExpiry()` / `extractClaimsUnsafe()` parse with a key-less parser, so a signing-config error can no longer be reinterpreted as "expired"/`null`. Added security docblocks noting these helpers do **not** verify the signature.
- `withParamData()` and `withClaims()` reject reserved claim names; split mode rethrows lcobucci's `RegisteredClaimGiven` as a descriptive `JWTConfigurationException`.
- `jwt:key` now requires at least **32 bytes** (256-bit, the HS256 floor) and returns explicit exit codes; `jwt:keypair` validates `--curve` and suggests the matching ES256/384/512 signer.

### Performance
- The stateless signer + key `Configuration` is now memoized per instance, so `decode()` no longer rebuilds it twice (nor re-reads the asymmetric PEM from disk) per call. Time-dependent constraints are still rebuilt per call.

## [3.1.0] — 2026-06-02

### Added
- New `JWT::withExpiresAt(string $modifier)` method — a per-instance override of the `expiresAt` modifier (e.g. `JWT::for()->withExpiresAt('+5 minutes')->encode($data)` for short-lived access tokens) **without** mutating the shared config. Returns a new instance, mirroring `withLeeway()` / `withSplitData()` / `withParamData()`. Throws `InvalidArgumentException` on an empty string.
- `JWTConfigurationException::missingSignatureConstraint()` and `JWTConfigurationException::algorithmMismatch()` factories backing the new fail-fast checks below.

### Changed
- `JWT::withLeeway(?int $seconds)` now accepts `null` to reset to "no leeway"; a negative int still throws `InvalidArgumentException`.
- `Config\JWT::$uid` is now `int|string|null` (was `?string`) and `JWT::encode(mixed $data, int|string|null $uid = null)` accepts an integer or string uid. An integer uid (e.g. a DB primary key) round-trips as an integer because `lcobucci/jwt` preserves the JSON type.
- Empty-string `issuer` / `audience` / `identifier` now throw `JWTConfigurationException`, the same as `null` (previously only `null` was rejected).
- Every file under `src/` now declares `declare(strict_types=1)`.

### Fixed
- `decode()` now refuses to silently skip signature verification: if `Config\JWT::$validateClaims` does **not** contain `'SignedWith'` while `$validate = true`, it throws `JWTConfigurationException`. To decode without any validation, set `Config\JWT::$validate = false`.
- `decode()` with `$validate = false` now logs a `warning` via `log_message()` (parallel to `extractClaimsUnsafe()`); it is intended for tests / debug only.
- A mismatch between `$algorithmType` and the `$algorithm` signer class — e.g. `algorithmType = 'asymmetric'` left on the default HMAC `Sha256`, or `algorithmType = 'symmetric'` with an RSA/ECDSA signer — now throws `JWTConfigurationException` with a clear message instead of a cryptic `lcobucci` "key" error. Symmetric requires an `Lcobucci\JWT\Signer\Hmac\*` signer; asymmetric requires `Rsa\*` or `Ecdsa\*`.
- Invalid `canOnlyBeUsedAfter` **and** `expiresAt` `DateTimeImmutable::modify()` strings now both throw `InvalidArgumentException` consistently, normalising the PHP 8.2 (returns `false`) and 8.3+ (throws) failure modes. A *valid* future `canOnlyBeUsedAfter` is still clamped to issuance time so freshly-issued tokens are immediately usable (intended).
- `jwt:keypair`: on Windows the command now warns that `chmod()` cannot enforce file permissions (recommends restricting access via NTFS ACLs, e.g. `icacls`), and warns when `--passphrase` is passed on the command line because that value can leak via the process list and shell history.

## [3.0.0] — 2026-05-08

The 3.0 release rebuilds the library on top of `lcobucci/jwt 5`, hardens defaults, fixes several latent bugs, and introduces asymmetric (RSA / ECDSA) support. See [Migration v2 → v3](docs/migration-v2-to-v3.md) for the upgrade path.

### Added
- First-class **RSA and ECDSA** support via `Config\JWT::$algorithmType = 'asymmetric'` plus `signingKey` / `verifyingKey` / `passphrase`.
- New CLI command `php spark jwt:keypair` to generate RSA or ECDSA key pairs (with optional passphrase, custom curve, custom output path).
- New `JWT::tryDecode()` method that returns `?Plain` instead of throwing — replaces the v2.x `throwable=false` flag.
- New `JWT::getPayload()` method that validates the token **and** returns the original payload value (auto `json_decode` for compact-mode arrays, transparently). Tokens generated by v3 carry header `cty=json` to make this round-trip explicit.
- New `JWT::for(?JWTConfig)` static factory.
- New immutable `with*()` API: `withSplitData()`, `withParamData()`, `withLeeway()`. Each call returns a new instance.
- New `Config\JWT::$leeway` (and per-call `withLeeway()`) for tolerating clock skew on `iat` / `nbf` / `exp`.
- New `Config\JWT::$allowUnsafeExtraction` flag. While `false` (the default), every call to `extractClaimsUnsafe()` writes a warning to the framework logger so accidental production usage is visible.
- New `Daycry\JWT\Exceptions\JWTConfigurationException` with `missingSigner()` / `missingClaim($name)` / `invalidAlgorithmType()` / `unknownConstraint()` factories.
- New `Daycry\JWT\Exceptions\InvalidTokenException` thrown for malformed (non-parseable) tokens.
- `phpstan.neon.dist` (level 8 + strict-rules) and a baseline file.
- `psalm.xml` (errorLevel 6) with a baseline file.
- `rector.php` (PHP 8.2 level set, dead-code, code-quality, early-return, PHPUnit code-quality sets).
- One workflow per quality gate (`phpunit.yml`, `phpstan.yml`, `psalm.yml`, `rector.yml`, `code-style.yml`, `codeql.yml`) so each tool publishes its own status badge.
- `SECURITY.md`, `CONTRIBUTING.md`, `.github/ISSUE_TEMPLATE/`.
- `docs/migration-v2-to-v3.md`, `docs/threat-model.md`, plus an `examples/` folder with middleware, refresh-token rotation and multi-tenant verifier patterns.

### Changed
- **BREAKING**: minimum PHP is now `^8.2` (was `^8.1`).
- **BREAKING**: minimum `lcobucci/jwt` is now `^5.5` (was `^4`).
- **BREAKING**: `JWT::decode()` now returns `Lcobucci\JWT\Token\Plain` (was `DataSet|RequiredConstraintsViolated`). Read claims via `$token->claims()->get('name')`.
- **BREAKING**: `Config\JWT::$signer`, `$issuer`, `$audience`, `$identifier` no longer have non-null defaults; the library throws `JWTConfigurationException` when they are missing. The previously published example signing key is gone.
- **BREAKING**: `JWT::setSplitData()` and `JWT::setParamData()` are removed; use the immutable `withSplitData()` / `withParamData()` instead.
- **BREAKING**: `Config\JWT::$throwable` flag removed. Use `decode()` (always throws) and `tryDecode()` (returns `?Plain`).
- **BREAKING**: `JWT::clearCache()` removed. The library no longer caches state across calls (the v2.x `FrozenClock` cache was the source of a real bug; see Fixed).
- `JWT` is `final` and immutable.
- `JWT::encode()` adds the JWT header `cty=json` when serialising arrays/objects in compact mode, making `getPayload()` symmetric.
- The `Config\JWT::$validateClaims` `'ValidAt'` entry now resolves to `LooseValidAt` under the hood. Use `'StrictValidAt'` explicitly to require all of `iat` / `nbf` / `exp`.
- `JWTPublish` command no longer calls `exit()`; it returns proper `EXIT_*` codes and lets the framework handle the lifecycle.
- The full documentation set (`README.md`, `docs/index.md`, `docs/usage.md`, `docs/configuration.md`, `docs/commands.md`, `docs/advanced.md`) has been rewritten for v3.

### Removed
- `Config\JWT::$throwable`.
- `JWT::clearCache()`.
- `JWT::setSplitData()`, `JWT::setParamData()` (replaced by `with*()`).
- The hard-coded default signing key in `Config\JWT::$signer`.
- Three duplicate test files for `JWTGenerateKey` (`*DirectTest`, `*LogicTest`, `*ActualTest`). Replaced by a focused suite that actually invokes the command.

### Fixed
- **Critical**: validation no longer reuses a `FrozenClock` captured on the first `decode()` call. In v2.x a long-lived `JWT` instance (worker, daemon, queue handler) would validate already-expired tokens as fresh, because `ValidAt` was cached together with its clock. Constraints are now built per call.
- `encode($data, uid: 0)` now stores `uid=0` in the token. Previously the integer `0` was treated as "no uid" because of a `if ($uid)` truthy check.
- `JWTPublish` correctly handles `realpath()` returning `false` and `Autoload::$psr4` returning a list, instead of producing implicit string casts.

### Security
- The package no longer ships with a hard-coded signing key in either source code or documentation. Installations that previously relied on the default value will now fail to encode/decode tokens until `php spark jwt:key` is run, surfacing the misconfiguration loudly.
- `extractClaimsUnsafe()` writes a warning to the framework logger by default, making accidental production use visible.

## [2.0.1] — 2026-03-03

Last release of the 2.x line. Added `docs/` directory and rewrote
`README.md` with installation notes, usage examples, and CLI reference.
Updated `phpunit.xml.dist` and `tests/Commands/JWTGenerateKeyActualTest.php`
to match. Still targets `lcobucci/jwt ^4`. No longer maintained — upgrade
to 3.x.

## [2.0.0] — earlier 2026

First 2.x release.

## [1.x.y] — pre-2.0

Earlier releases targeted `lcobucci/jwt ^4` and exposed the union-typed `decode()` API. They are no longer maintained — please upgrade to 3.x.

[3.2.0]: https://github.com/daycry/jwt/releases/tag/v3.2.0
[3.1.0]: https://github.com/daycry/jwt/releases/tag/v3.1.0
[3.0.0]: https://github.com/daycry/jwt/releases/tag/v3.0.0
[2.0.1]: https://github.com/daycry/jwt/releases/tag/v2.0.1
[2.0.0]: https://github.com/daycry/jwt/releases/tag/v2.0.0
