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

`JWT` is **immutable**: `withSplitData()`, `withParamData()`, `withLeeway()` all return new instances.

```php
$base    = JWT::for();
$splitter = $base->withSplitData();      // new instance
$base->isSplitData();                    // false (unchanged)
$splitter->isSplitData();                // true
```

---

## encode()

```php
public function encode(mixed $data, mixed $uid = null): string
```

| Parameter | Type | Description |
|---|---|---|
| `$data` | `mixed` | Payload. Scalar, array, or object. |
| `$uid` | `mixed` | Override for the `uid` claim. Defaults to `Config\JWT::$uid`. Pass `0` if you actually want zero — only `null` and `''` skip the claim. |

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

## withSplitData() / withParamData() / withLeeway()

Immutable configuration:

```php
$jwt = JWT::for()
    ->withSplitData()                  // each array key becomes its own claim
    ->withParamData('payload')         // change the default 'data' claim name
    ->withLeeway(30);                  // ±30 seconds of clock skew tolerance

echo $jwt->getParamData();             // 'payload'
$jwt->isSplitData();                   // true
```

Each call returns a fresh instance — the original is untouched.

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
| `ValidAt` (alias) | → `LooseValidAt` | Provided for v1.x config compatibility |

```php
$config->validateClaims = ['SignedWith', 'IssuedBy', 'StrictValidAt'];
$jwt = new JWT($config);
```
