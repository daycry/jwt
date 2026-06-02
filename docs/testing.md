# Testing

> **[← Back to index](index.md)**

---

## Running the Test Suite

### Full suite with coverage

```bash
composer test
# or
vendor/bin/phpunit
```

Coverage reports are written to:

| Format | Path |
|---|---|
| HTML | `build/coverage/html/index.html` |
| Clover XML | `build/logs/clover.xml` |
| Plain text | `build/coverage/coverage.txt` |

### Without coverage (faster)

```bash
vendor/bin/phpunit --no-coverage
```

### Single test class

```bash
vendor/bin/phpunit --filter JWTTest
vendor/bin/phpunit --filter JWTGenerateKeyTest
vendor/bin/phpunit --filter JWTPublishTest
vendor/bin/phpunit --filter JWTPerformanceTest
```

### Single test method

```bash
vendor/bin/phpunit --filter "/testJWTEncodeString$/"
```

---

## Test Suite Structure

```
tests/
├── Commands/
│   ├── JWTGenerateKeyTest.php           ← jwt:key command (StreamFilter + reflection)
│   ├── JWTKeyPairTest.php               ← jwt:keypair command (skips without OpenSSL)
│   ├── JWTKeyPairWriteKeyFileTest.php   ← writeKeyFile() in isolation (runs on Windows)
│   └── JWTPublishTest.php               ← jwt:publish command tests
├── Exceptions/
│   └── JWTConfigurationExceptionTest.php ← exception factory messages
├── Performance/
│   └── JWTPerformanceTest.php           ← timing assertions
└── Validators/
    ├── AsymmetricTest.php               ← RSA/ECDSA round-trips (skips without OpenSSL)
    ├── JWTTest.php                      ← core encode/decode/validation tests
    └── SecurityTest.php                 ← security guards, edge cases, fail-closed config
```

---

## Test Configuration

PHPUnit is configured in [`phpunit.xml.dist`](../phpunit.xml.dist) with:

```xml
<env name="CI_ENVIRONMENT" value="testing"/>
```

This causes `JWTGenerateKey::updateEnvFile()` to skip interactive prompts and overwrite `.env` silently (same as `--force`).

### Environment variables available in tests

| Variable | Value | Purpose |
|---|---|---|
| `CI_ENVIRONMENT` | `testing` | Activates test-safe behaviour in commands |
| `jwt.signer` | (base64 string) | Default signing key for test tokens |
| `jwt.issuer` | `https://test.example.com` | Issuer claim in test tokens |
| `jwt.audience` | `https://test.example.com` | Audience claim in test tokens |
| `jwt.identifier` | `jwt-test-app` | Identifier claim in test tokens |
| `jwt.expiresAt` | `+1 hour` | Token lifetime during tests |
| `CLI_NO_PROMPT` / `AUTO_ANSWER` | `true` / `y` | Auto-answer CLI prompts in tests |

---

## Test Coverage by Area

### `tests/Validators/JWTTest.php`

Covers the core `JWT` class public API:

| Test | What it verifies |
|---|---|
| `testJWTEncodeString` | Scalar payload stored in `data` claim |
| `testJWTEncodeStringWithCustomUid` | `uid` override via second argument |
| `testJWTEncodeStringPicksUpDefaultUid` | `uid` taken from config |
| `testJWTEncodeStringDefaultConfig` | `JWT::for()` falls back to `config('JWT')` |
| `testJWTEncodeArrayWithoutSplit` | Integer and associative arrays as JSON in `data` |
| `testJWTEncodeArrayWithSplit` | Array keys spread as individual claims |
| `testDecodeThrowsOnWrongIdentifier` | `RequiredConstraintsViolated` is thrown on an invalid token |
| `testTryDecodeReturnsNullOnFailure` | `tryDecode()` returns `null` (does not throw) on failure |
| `testJWTValidationConstraintsAllPass` | All 5 constraints pass on a freshly encoded token |
| `testJWTValidationDisabled` | `validate=false` bypasses all constraint checks |
| `testJWTPartialValidationConstraints` | Subset of constraints (`SignedWith`, `LooseValidAt`) |
| `testWithLeewayAcceptsNullToResetLeeway` | `withLeeway(null)` resets leeway to "no leeway" |
| `testWithExpiresAtOverridesConfiguredLifetime` | `withExpiresAt()` overrides the configured token lifetime |

### `tests/Performance/JWTPerformanceTest.php`

| Test | What it verifies |
|---|---|
| `testLazyLoadingPerformance` | 100 `JWT` instantiations complete in < 100 ms |
| `testConstraintsCaching` | Second `decode()` is faster than first (cache hit) |
| `testFastValidationMethods` | `isValid()`, `isExpired()`, `getTimeToExpiry()` complete within time bounds |

### `tests/Commands/JWTGenerateKeyTest.php`

Structural and logic tests using reflection — no actual `.env` or CLI side effects:

| Test | What it verifies |
|---|---|
| `testKeyGeneration` | `random_bytes(32)` produces a 44-char Base64 string |
| `testKeyLengthValidation` | 16/32/64 bytes produce expected Base64 lengths |
| `testKeyUniqueness` | 10 consecutive keys are all different |
| `testBase64Validation` | Generated key round-trips through base64_decode |
| `testCommandClassExists` | Class exists, has `run()`, extends `BaseCommand` |
| `testCommandInstantiation` | Properties (`group`, `name`, `description`) have expected values |
| `testCommandWithShowOption` | `run()` accepts a single `$params` array |
| `testPrivateMethodsViaReflection` | `displayKey` and `updateEnvFile` have correct signatures |

### `tests/Commands/JWTGenerateKeyActualTest.php`

Integration-style coverage that actually calls command code:

| Test | What it verifies |
|---|---|
| `testCommandInstantiation` | Full constructor via real `Logger` + `Commands` instances |
| `testRunMethodWithValidLength` | `run(['32', '--show'])` executes without fatal errors |
| `testRunMethodWithInvalidLength` | `run(['8', '--show'])` exercises length validation error path |
| `testRunMethodWithForceFlag` | `--force` flag code path is reached |
| `testRunMethodWithDifferentLengths` | Lengths 16–64 all enter the key-generation branch |

### `tests/Commands/JWTPublishTest.php`

| Test | What it verifies |
|---|---|
| `testCommandClassExists` | Class exists, has `run()`, extends `BaseCommand` |
| `testCommandInstantiation` | Full constructor with real dependencies |
| `testProtectedMethodsViaReflection` | `determineSourcePath`, `publishConfig`, `writeFile` exist with correct visibility |
| `testSourcePathDetermination` | Source path resolves and `Config/JWT.php` is present at that location |
| `testConfigContentTransformation` | Namespace and parent-class replacements are applied correctly |

---

## Writing Tests for This Library

### Disable `LooseValidAt` for deterministic tests

The `LooseValidAt` constraint checks that the current time falls inside `[nbf, exp]`. This can cause flaky tests on slow CI machines. Exclude it from `validateClaims` in your test's `setUp()` — but keep `SignedWith`, otherwise `decode()` throws `JWTConfigurationException` (it refuses to skip signature verification):

```php
protected function setUp(): void
{
    parent::setUp();

    $this->config = config('JWT');
    $this->config->validateClaims = [
        'SignedWith',
        'IssuedBy',
        'IdentifiedBy',
        'PermittedFor',
        // 'LooseValidAt' intentionally omitted
    ];
    $this->library = new JWT($this->config);
}
```

### Inject config directly

Avoid relying on global `config('JWT')` in unit tests — inject a local instance instead:

```php
$config             = new \Daycry\JWT\Config\JWT();
$config->signer     = base64_encode(random_bytes(32));
$config->issuer     = 'https://test.example.com';
$config->audience   = 'https://test.example.com';
$config->identifier = 'test-id';
$config->validate   = false; // fastest possible decoding for unit tests

$jwt = new JWT($config);
```

### Testing exception behaviour

```php
public function testDecodeThrowsOnExpiredToken(): void
{
    $config            = new \Daycry\JWT\Config\JWT();
    $config->expiresAt = '-1 second'; // immediately expired
    $jwt               = new JWT($config);

    $token = $jwt->encode('payload');

    // Re-enable LooseValidAt so expiry is checked
    $config->validateClaims = ['SignedWith', 'LooseValidAt'];
    $strictJwt = new JWT($config);

    $this->expectException(\Lcobucci\JWT\Validation\RequiredConstraintsViolated::class);
    $strictJwt->decode($token);
}
```

---

## PHPUnit Configuration Notes

The project uses PHPUnit 11. The following attributes from older versions are **not** present and should not be re-added:

| Removed attribute | Reason |
|---|---|
| `beStrictAboutOutputDuringTests` | Removed in PHPUnit 11 |
| `<coverage includeUncoveredFiles>` | Removed in PHPUnit 11 |
| `<coverage ignoreDeprecatedCodeUnits>` | Removed in PHPUnit 11 |
| `<coverage disableCodeCoverageIgnore>` | Removed in PHPUnit 11 |

`@depends` docblock annotations are deprecated in PHPUnit 11. Use the PHP 8 native attribute instead:

```php
// Deprecated — do not use
/** @depends testCommandInstantiation */
public function testSomething($fixture) {}

// Correct for PHPUnit 11
use PHPUnit\Framework\Attributes\Depends;

#[Depends('testCommandInstantiation')]
public function testSomething($fixture) {}
```
