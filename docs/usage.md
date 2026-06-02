# Usage

> **[← Back to index](index.md)**

---

## Instantiation

```php
use Daycry\JWT\JWT;

$jwt = JWT::for();           // resolves config('JWT') for you

// or, with an explicit config (tests, multi-tenant)
use Daycry\JWT\Config\JWT as JWTConfig;

$config         = new JWTConfig();
$config->signer = '...';
$config->issuer = 'https://api.my-app.com';
// ...
$jwt = new JWT($config);
```

`JWT` is **immutable**: `withSplitData()`, `withParamData()`, `withLeeway()`, `withExpiresAt()` all return new instances.

```php
$base    = JWT::for();
$splitter = $base->withSplitData();      // new instance
$base->isSplitData();                    // false (unchanged)
$splitter->isSplitData();                // true
```

---

## encode()

```php
public function encode(mixed $data, int|string|null $uid = null): string
```

| Parameter | Type | Description |
|---|---|---|
| `$data` | `mixed` | Payload. Scalar, array, or object. |
| `$uid` | `int\|string\|null` | Override for the `uid` claim. Defaults to `Config\JWT::$uid`. Accepts a string **or** an integer ID (e.g. a DB primary key) — `lcobucci` preserves the JSON type, so an integer `uid` round-trips as an integer. Pass `0` if you actually want zero — only `null` and `''` skip the claim. |

> The standard claims (`iss`, `aud`, `jti`) come from `Config\JWT::$issuer`, `$audience` and `$identifier`. Each one is required: a `null` **or empty-string** value throws `Daycry\JWT\Exceptions\JWTConfigurationException` (run `php spark jwt:publish` and fill them in).

### Standard claims added automatically

| Claim | Source |
|---|---|
| `iss` | `Config\JWT::$issuer` |
| `aud` | `Config\JWT::$audience` |
| `jti` | `Config\JWT::$identifier` |
| `iat` | Current timestamp |
| `nbf` | `iat + Config\JWT::$canOnlyBeUsedAfter` |
| `exp` | `iat + Config\JWT::$expiresAt` |
| `cty` (header) | `'json'` — only for compact-mode array payloads |

### Scalar payload

```php
$token = $jwt->encode('hello');                      // claims: { "data": "hello", ... }
```

### Array payload — compact (default)

```php
$token   = $jwt->encode(['user_id' => 1, 'role' => 'admin']);
$payload = $jwt->getPayload($token);                 // ['user_id' => 1, 'role' => 'admin']
```

The array is JSON-encoded into the single `data` claim (or the name set via `withParamData()`). The token carries header `cty=json` so `getPayload()` can auto-decode it.

### Array payload — split mode

```php
$jwt   = JWT::for()->withSplitData();
$token = $jwt->encode(['user_id' => 1, 'role' => 'admin']);

$decoded = $jwt->decode($token);
echo $decoded->claims()->get('role');                // "admin"
```

In split mode each entry becomes its own top-level claim. **Avoid keys that collide with registered claims** (`iss`, `aud`, `exp`, etc.) — `Builder::withClaim()` throws `RegisteredClaimGiven` for those.

---

## decode()

```php
public function decode(string $token): \Lcobucci\JWT\Token\Plain
```

Parses and validates the token. **Always throws** on failure:

- `Daycry\JWT\Exceptions\InvalidTokenException` — malformed input.
- `Lcobucci\JWT\Validation\RequiredConstraintsViolated` — at least one constraint failed.
- `Daycry\JWT\Exceptions\JWTConfigurationException` — `Config\JWT::$validateClaims` does not contain `SignedWith` while `$validate = true`. The library refuses to silently skip signature verification. To decode without any validation, set `Config\JWT::$validate = false`.

When `Config\JWT::$validate = false`, `decode()` skips validation **and logs a `warning`** via `log_message()` (parallel to `extractClaimsUnsafe()`) — it is intended for tests/debug only, never production.

```php
use Daycry\JWT\Exceptions\InvalidTokenException;
use Lcobucci\JWT\Validation\RequiredConstraintsViolated;

try {
    $token = $jwt->decode($input);
    echo $token->claims()->get('uid');
} catch (RequiredConstraintsViolated $e) {
    // signature, issuer, audience, exp, jti…
} catch (InvalidTokenException $e) {
    // not a JWT at all
}
```

### Reading claims and headers

```php
$decoded = $jwt->decode($token);

$decoded->claims()->get('uid');          // custom uid
$decoded->claims()->get('iss');          // issuer
$decoded->claims()->get('exp');          // DateTimeImmutable
$decoded->claims()->all();               // associative array

$decoded->headers()->get('alg');         // signing algorithm
$decoded->headers()->get('cty');         // 'json' if compact-mode array payload
```

---

## tryDecode()

```php
public function tryDecode(string $token): ?\Lcobucci\JWT\Token\Plain
```

Same as `decode()` but returns `null` instead of throwing. Use this in middleware where you want to short-circuit on bad tokens without try/catch.

```php
$decoded = $jwt->tryDecode($input);
if ($decoded === null) {
    return $this->response->setStatusCode(401);
}
```

---

## getPayload()

```php
public function getPayload(string $token): mixed
```

Validate **and** return the original payload value:

- Scalar → returned as is.
- Compact-mode array (header `cty=json`) → already `json_decode`d back into an array.
- Split-mode → returns the value of `paramData` (often `null` since data is spread across many claims).

```php
$jwt->getPayload($jwt->encode('hello'));                          // "hello"
$jwt->getPayload($jwt->encode(['x' => 1]));                       // ['x' => 1]
```

Throws the same exceptions as `decode()`.

---

## withSplitData() / withParamData() / withLeeway() / withExpiresAt()

Immutable configuration:

```php
$jwt = JWT::for()
    ->withSplitData()                  // each array key becomes its own claim
    ->withParamData('payload')         // change the default 'data' claim name
    ->withLeeway(30)                   // ±30 seconds of clock skew tolerance
    ->withExpiresAt('+5 minutes');     // override the configured expiresAt modifier

echo $jwt->getParamData();             // 'payload'
$jwt->isSplitData();                   // true
```

Each call returns a fresh instance — the original is untouched.

### withLeeway(?int $seconds)

Accepts an integer number of seconds, or `null` to reset back to "no leeway". A **negative** int throws `InvalidArgumentException`.

```php
$jwt->withLeeway(30);                  // ±30 seconds
$jwt->withLeeway(null);                // no leeway
```

### withExpiresAt(string $modifier)

Per-instance override of the `expiresAt` modifier — mint short-lived access tokens **without** mutating the shared `Config\JWT::$expiresAt`. The modifier is any string accepted by `DateTimeImmutable::modify()` (an invalid modifier throws `InvalidArgumentException` at encode time). An empty string throws `InvalidArgumentException` immediately.

```php
$access = JWT::for()->withExpiresAt('+5 minutes')->encode($data);   // short-lived
$refresh = JWT::for()->encode($data);                                // uses Config::$expiresAt
```

---

## Validation constraints

Controlled by `Config\JWT::$validateClaims`. Allowed values:

| Name | Library class | Notes |
|---|---|---|
| `SignedWith` | `Lcobucci\JWT\Validation\Constraint\SignedWith` | Signature verification |
| `IssuedBy` | `Lcobucci\JWT\Validation\Constraint\IssuedBy` | `iss` |
| `IdentifiedBy` | `Lcobucci\JWT\Validation\Constraint\IdentifiedBy` | `jti` |
| `PermittedFor` | `Lcobucci\JWT\Validation\Constraint\PermittedFor` | `aud` |
| `LooseValidAt` (default) | `Lcobucci\JWT\Validation\Constraint\LooseValidAt` | `iat`/`nbf`/`exp` with leeway |
| `StrictValidAt` | `Lcobucci\JWT\Validation\Constraint\StrictValidAt` | `iat`/`nbf`/`exp` requiring all three |
| `ValidAt` (alias) | → `LooseValidAt` | Provided for v2.x config compatibility |

```php
$config->validateClaims = ['SignedWith', 'IssuedBy', 'StrictValidAt'];
$jwt = new JWT($config);
```
