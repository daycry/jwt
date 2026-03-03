# Usage

> **[← Back to index](index.md)**

---

## Instantiation

```php
use Daycry\JWT\JWT;

// Uses config('JWT') resolved from app/Config/JWT.php or .env
$jwt = new JWT();

// Inject a custom config object (useful in tests or multi-tenant scenarios)
$jwt = new JWT($config);
```

---

## encode()

```php
public function encode(mixed $data, ?string $uid = null): string
```

Produces a signed JWT string.

### Parameters

| Parameter | Type | Description |
|---|---|---|
| `$data` | `mixed` | The payload. Scalar, array, or object. |
| `$uid` | `?string` | Custom `uid` claim value. Defaults to `$config->uid`. |

### Return value

A signed JWT string ready to be sent as a Bearer token.

### Standard claims added automatically

| Claim | Source |
|---|---|
| `iss` | `$config->issuer` |
| `aud` | `$config->audience` |
| `jti` | `$config->identifier` |
| `iat` | Current timestamp |
| `nbf` | `iat + $config->canOnlyBeUsedAfter` |
| `exp` | `iat + $config->expiresAt` |
| `type` header | `"Bearer"` |

### Scalar payload

Stored in the `data` claim (or the name set by `setParamData()`):

```php
$token = $jwt->encode('hello');
// claims: { "data": "hello", "uid": null, "iss": ..., ... }
```

### Array payload — compact mode (default)

The entire array is JSON-encoded and stored in a single `data` claim:

```php
$token = $jwt->encode(['user_id' => 1, 'role' => 'admin']);
// claims: { "data": "{\"user_id\":1,\"role\":\"admin\"}", ... }

$claims = $jwt->decode($token);
$payload = json_decode($claims->get('data'), true);
```

### Array payload — split mode

Each key becomes its own top-level claim:

```php
$jwt->setSplitData();
$token = $jwt->encode(['user_id' => 1, 'role' => 'admin']);
// claims: { "user_id": 1, "role": "admin", ... }

$claims = $jwt->decode($token);
echo $claims->get('user_id'); // 1
echo $claims->get('role');    // "admin"
```

### Custom `uid`

```php
$token = $jwt->encode($payload, 'user-42');
// claims: { ..., "uid": "user-42" }
```

---

## decode()

```php
public function decode(string $data): DataSet|RequiredConstraintsViolated
```

Parses and optionally validates a JWT string.

### Parameters

| Parameter | Type | Description |
|---|---|---|
| `$data` | `string` | A JWT string produced by `encode()` or any compatible issuer. |

### Return value

- **`Lcobucci\JWT\Token\DataSet`** on success — call `->get('claimName')` to read individual claims.
- **`Lcobucci\JWT\Validation\RequiredConstraintsViolated`** when validation fails and `$config->throwable = false`.
- Throws **`RequiredConstraintsViolated`** when validation fails and `$config->throwable = true` (default).

### Reading decoded claims

```php
$claims = $jwt->decode($token);

$claims->get('data');          // custom payload claim
$claims->get('uid');           // uid claim
$claims->get('iss');           // issuer
$claims->get('aud');           // audience (returns array)
$claims->get('jti');           // identifier
$claims->get('iat');           // issued-at (DateTimeImmutable)
$claims->get('nbf');           // not-before (DateTimeImmutable)
$claims->get('exp');           // expires-at (DateTimeImmutable)
$claims->all();                // all claims as associative array
```

### Handling validation errors

**With `throwable = true` (default):**

```php
use Lcobucci\JWT\Validation\RequiredConstraintsViolated;

try {
    $claims = $jwt->decode($token);
} catch (RequiredConstraintsViolated $e) {
    // $e->getMessage() describes which constraints failed
    return $this->response->setStatusCode(401)->setJSON([
        'error' => 'Unauthorized',
        'detail' => $e->getMessage(),
    ]);
}
```

**With `throwable = false`:**

```php
$config->throwable = false;
$jwt    = new JWT($config);
$result = $jwt->decode($token);

if ($result instanceof RequiredConstraintsViolated) {
    echo $result->getMessage(); // "The token violates some mandatory constraints..."
} else {
    $payload = $result->get('data');
}
```

---

## setParamData()

```php
public function setParamData(string $claimName): JWT
```

Changes the claim name used to store scalar and compact-array payloads (default: `'data'`).

```php
$jwt->setParamData('payload');
$token  = $jwt->encode('hello');
$claims = $jwt->decode($token);

echo $claims->get('payload'); // "hello"
```

Returns `$this` for method chaining.

---

## getParamData()

```php
public function getParamData(): string
```

Returns the current payload claim name.

---

## setSplitData()

```php
public function setSplitData(bool $value = true): JWT
```

Enables or disables split-array mode. When enabled, array keys are stored as individual top-level claims. When disabled (default), the array is JSON-encoded into a single claim.

```php
$jwt->setSplitData();           // enable
$jwt->setSplitData(false);      // disable
```

Returns `$this` for method chaining.

---

## Fluent Interface

`setParamData()` and `setSplitData()` both return `$this`, so they can be chained:

```php
$token = (new JWT())
    ->setSplitData()
    ->setParamData('custom')   // ignored when split mode is on
    ->encode(['a' => 1, 'b' => 2]);
```

---

## Validation Constraints

The constraints evaluated during `decode()` are controlled by `$config->validateClaims`. See [Configuration — `$validateClaims`](configuration.md#validateclaims) for the full list.

You can change the active constraints at runtime without rebuilding the `JWT` instance — the cache is keyed by the constraint list, so different configurations are handled independently:

```php
$config = config('JWT');

// Strict — all constraints
$config->validateClaims = ['SignedWith', 'IssuedBy', 'ValidAt', 'IdentifiedBy', 'PermittedFor'];
$jwtStrict = new JWT($config);

// Loose — signature only
$config2 = clone $config;
$config2->validateClaims = ['SignedWith'];
$jwtLoose = new JWT($config2);
```
