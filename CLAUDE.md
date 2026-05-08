# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this package is

`daycry/jwt` is a thin wrapper around `lcobucci/jwt ^5.5` for CodeIgniter 4. It exposes one immutable façade (`Daycry\JWT\JWT`), one config class (`Daycry\JWT\Config\JWT`) and three Spark commands (`jwt:key`, `jwt:keypair`, `jwt:publish`). It is published on Packagist as `daycry/jwt`.

The current `master` is the work-in-progress **v3.0** rewrite. The latest published release on Packagist is **v2.0.1** (2026-03-03), which still targets `lcobucci/jwt ^4` with a mutable API. Treat the migration target documented in `docs/migration-v2-to-v3.md` as authoritative when reasoning about breaking changes — the older "v1 → v2" framing in commit messages predates the realisation that v2.x already shipped.

## Common commands

All commands assume PHP 8.2+. Composer scripts wrap the bare binaries:

```
composer test                  # PHPUnit suite (writes coverage to build/coverage/)
composer test -- --no-coverage # faster local run, no Xdebug
composer test -- --filter SecurityTest      # single class
composer test -- --filter testZeroUidIsPreservedInToken  # single method

composer analyze     # PHPStan level 8 + strict-rules + codeigniter extension
composer psalm       # Psalm errorLevel 6
composer rector      # Rector dry-run
composer rector:fix  # apply Rector changes
composer cs-check    # php-cs-fixer dry-run (CodeIgniter4 standard)
composer cs-fix      # apply cs-fixer changes
composer qa          # everything: analyze + psalm + rector + cs-check + test
```

The project uses **baselines** for both PHPStan (`phpstan-baseline.neon`) and Psalm (`psalm-baseline.xml`). When a refactor renames or removes code, regenerate the affected baseline rather than ignore unmatched errors:

```
vendor/bin/phpstan analyze --no-progress --generate-baseline=phpstan-baseline.neon --allow-empty-baseline
vendor/bin/psalm --set-baseline=psalm-baseline.xml --no-progress
```

PHP version drift between local and CI surfaces here: PHP 8.5 emits some `identical.alwaysFalse` warnings (e.g. for `DateTimeImmutable::modify() === false`) that PHP 8.2 does not. Suppress those with `reportUnmatched: false` in `phpstan.neon.dist`, not in the baseline.

## Architecture

The runtime surface area is intentionally small:

- **`src/JWT.php`** — `final` immutable façade. `__construct(JWTConfig)` is the only way to inject config explicitly; `JWT::for()` is a static factory that falls back to `config('JWT')`. Every customiser returns a new instance: `withSplitData()`, `withParamData()`, `withLeeway()`. The class has two main paths inside `buildConfiguration()`:
  - `algorithmType = 'symmetric'` → `Configuration::forSymmetricSigner` with `InMemory::base64Encoded($signer)`.
  - `algorithmType = 'asymmetric'` → `Configuration::forAsymmetricSigner` with `InMemory::file()` or `InMemory::plainText()` resolved by `loadKey()` (auto-detects PEM-on-disk vs raw PEM string vs `file://` prefix).
- **`src/Config/JWT.php`** — extends `CodeIgniter\Config\BaseConfig`. CI4 maps env variables to properties using the `shortPrefix.property` convention, so `.env` keys are `jwt.signer`, `jwt.issuer`, etc. (lowercase, dot-separated). All required fields default to `null`; the library throws `JWTConfigurationException` on first use until they are set, by design.
- **Validation flow** — `decode()` always throws (`InvalidTokenException` on parse errors, `RequiredConstraintsViolated` on validation failures). `tryDecode()` is the non-throwing variant returning `?Plain`. `getPayload()` is the symmetric companion to `encode()`: it inspects the `cty=json` header that compact-mode encoding writes and runs `json_decode` automatically.
- **Constraint construction** — `getValidationConstraints()` rebuilds `LooseValidAt` / `StrictValidAt` on every call. **Do not reintroduce caching of time-dependent constraints**: a previous version cached a `FrozenClock` and ended up validating already-expired tokens as fresh in long-lived processes (workers, daemons). Stateless constraints (`SignedWith`, `IssuedBy`, etc.) are fine to build per call too; the perceived microsecond saving is not worth the foot-gun.
- **Commands** (`src/Commands/`) — `JWTGenerateKey` (HMAC secret, writes to `.env`), `JWTKeyPair` (RSA/ECDSA pair to disk), `JWTPublish` (copies the package config into the host app, rewriting the namespace and inheritance). They are auto-registered via `extra.spark.commands` in `composer.json`.

## Testing notes

- The bootstrap (`vendor/codeigniter4/framework/system/Test/bootstrap.php`) is loaded via `phpunit.xml.dist`. The same file injects test env vars (`jwt.signer`, `jwt.issuer`, ...). When you write tests that mutate `config('JWT')`, remember other tests share the singleton — clone or override per test.
- **Asymmetric and `JWTKeyPair` tests skip on Windows** because PHP-CLI's bundled OpenSSL cannot find `openssl.cnf` and `openssl_pkey_new()` returns `false`. They run normally on Linux/macOS and in CI. Both test files use a `opensslIsUsable()` helper to call `markTestSkipped()` cleanly. Don't dump the skip — it's an environment limitation, not a quality issue.
- **CLI command testing pattern**: see `tests/Commands/JWTGenerateKeyTest.php`. The trick is `StreamFilterTrait` (captures `STDOUT`/`STDERR` for `CLI::write()`) plus reflection on `CLI::$options` to inject `--show` / `--force` without mutating `$_SERVER['argv']`. The `command()` helper does not propagate options reliably, so tests instantiate the command directly and call `run()`.
- **`JWTGenerateKey` env-file path injection**: the command exposes `protected envPath()` / `envExamplePath()` solely so a sandboxed subclass in tests can redirect IO into a `tempnam`-style directory without touching the project's real `.env`. If you ever need to test more file-side branches, extend that subclass — do not rewrite the production `.env` from a test.
- **`JWTPublish` error-path testing**: subclass the command and override `determineSourcePath()` / `publishConfig()` / `writeFile()` to throw or return `false` synthetically. `CLI::prompt()` reads from real stdin so it cannot be exercised directly; the `writeFile` override pattern covers that branch.
- The default `decode()` validation set includes `LooseValidAt`, so freshly-encoded tokens are validated against the system clock with no leeway. If you write a test that encodes and decodes back-to-back, do not artificially advance time inside the JWT instance.

## Editing pitfalls

- **`lcobucci/jwt 5` Builder is immutable.** Every `withClaim()` / `withHeader()` returns a new builder. The local pattern reassigns: `$builder = $builder->withClaim(...)`. Code that calls a builder method without reassigning silently drops the claim. PHPStan does not catch this — be deliberate when editing `JWT::encode()`.
- **`lcobucci/jwt 5` removed `LocalFileReference`.** Use `InMemory::file()` for paths and `InMemory::plainText()` for raw PEM. The `loadKey()` helper already does the right thing; reach for it instead of touching `lcobucci` types directly.
- **CI4 env-var convention** is `{shortPrefix}.{property}` (e.g. `jwt.signer`), not `JWT_SIGNER`. The `phpunit.xml.dist` env block follows the convention; keep it that way or `BaseConfig` won't see your overrides.
- **`if ($uid)` was a real bug** (rejected `0` and `'0'`). `JWT::encode()` checks `$uid !== null && $uid !== ''` instead. Don't simplify it back.
- **Default config values must stay `null`** for `signer`, `issuer`, `audience`, `identifier`, `signingKey`, `verifyingKey`. The previous v2.x line shipped a real-looking signing key as the default and that is why anyone who never ran `jwt:key` ended up signing with a known secret. The exception thrown by `JWTConfigurationException::missingSigner()` / `missingClaim()` is the intended UX.

## Workflows

CI runs one workflow per quality gate (`.github/workflows/`): `phpunit.yml` (matrix 8.2-8.5 + Coveralls upload from 8.2), `phpstan.yml`, `psalm.yml`, `rector.yml`, `code-style.yml`, `codeql.yml`. Each one publishes its own badge to the README. CodeQL only analyses the `actions` language (the workflow YAML files themselves) — PHP code analysis is covered by PHPStan + Psalm.
