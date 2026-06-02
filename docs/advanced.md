# Advanced

> **[← Back to index](index.md)**

---

## Utility Methods

### `tryDecode()`

```php
public function tryDecode(string $token): ?\Lcobucci\JWT\Token\Plain
```

Like `decode()` but returns `null` instead of throwing. Convenient inside middleware.

```php
$decoded = $jwt->tryDecode($input);
if ($decoded === null) {
    return service('response')->setStatusCode(401);
}
```

### `getPayload()`

```php
public function getPayload(string $token): mixed
```

Validate **and** return the original payload value:

- Scalar payloads → returned as-is.
- Compact-mode arrays (header `cty=json`) → already `json_decode`d back into an array.
- Split-mode → returns the value of `paramData` (often `null` since data is spread across many claims). Use `decode()` and inspect `claims()->all()` for split mode.

```php
$payload = $jwt->getPayload($token);
```

Throws the same exceptions as `decode()`.

### `isValid()`

```php
public function isValid(string $token): bool
```

`true` iff `tryDecode()` succeeds — never throws.

### `isExpired()`

```php
public function isExpired(string $token): bool
```

Cheap pre-flight check that inspects only the `exp` claim against `time()`. **Does not verify the signature.** Returns `true` for malformed tokens (defensive: treat unparseable as "no longer valid").

### `getTimeToExpiry()`

```php
public function getTimeToExpiry(string $token): ?int
```

Seconds remaining until `exp`, clamped at `0`. Returns `null` if the token cannot be parsed or has no `exp` claim.

```php
$ttl = $jwt->getTimeToExpiry($token);
if ($ttl !== null && $ttl < 300) {
    // warn the client to refresh
}
```

### `extractClaimsUnsafe()`

```php
public function extractClaimsUnsafe(string $token): ?array
```

Returns all claims as an associative array **without any validation**. Returns `null` if the token cannot be parsed.

> The library logs a warning each time this method is called unless you explicitly opt in with `Config\JWT::$allowUnsafeExtraction = true`. The flag exists to make accidental production usage visible in logs.

Use only when you have already verified the token through another mechanism, or when reading metadata (e.g. `iss` / `kid`) before deciding which key to verify with.

---

## Error handling

### `RequiredConstraintsViolated`

Validation failures (signature, claims, expiry, etc.) produce `Lcobucci\JWT\Validation\RequiredConstraintsViolated`. `getMessage()` lists the violated constraints.

### `InvalidTokenException`

Parsing failures (token is not three base64-encoded segments, encryption headers, etc.) produce `Daycry\JWT\Exceptions\InvalidTokenException`.

### `JWTConfigurationException`

Encoding without a configured `signer` / `signingKey` / `issuer` / `audience` / `identifier` produces `Daycry\JWT\Exceptions\JWTConfigurationException` with a message naming the missing field. An **empty string** counts as missing, just like `null`. The same exception is also thrown when:

- `$validateClaims` does not contain `'SignedWith'` while `$validate = true` (decoding refuses to silently skip signature verification — set `$validate = false` to decode without any validation);
- `$algorithm` does not match `$algorithmType` (`'symmetric'` needs an `Lcobucci\JWT\Signer\Hmac\*` signer; `'asymmetric'` needs an `Rsa\*` or `Ecdsa\*` signer);
- `$validateClaims` contains an unknown name (allowed: `SignedWith`, `IssuedBy`, `IdentifiedBy`, `PermittedFor`, `LooseValidAt` (alias `ValidAt`), `StrictValidAt`).

### `\JsonException`

`encode()` uses `JSON_THROW_ON_ERROR` for compact-mode arrays. Non-serialisable payloads (resources, recursive references) raise `\JsonException`.

### Combined try/catch

```php
use Daycry\JWT\Exceptions\InvalidTokenException;
use Daycry\JWT\Exceptions\JWTConfigurationException;
use Lcobucci\JWT\Validation\RequiredConstraintsViolated;

try {
    $claims = $jwt->decode($token);
} catch (JWTConfigurationException $e) {
    // Misconfiguration — operator-facing.
    log_message('error', $e->getMessage());
    throw $e;
} catch (RequiredConstraintsViolated $e) {
    return $this->respond(['error' => 'Invalid token', 'detail' => $e->getMessage()], 401);
} catch (InvalidTokenException $e) {
    return $this->respond(['error' => 'Bad token'], 400);
}
```

---

## Middleware pattern

```php
<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Daycry\JWT\JWT;

class JWTAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $header = $request->getHeaderLine('Authorization');

        if (! str_starts_with($header, 'Bearer ')) {
            return service('response')->setStatusCode(401)
                ->setJSON(['error' => 'Missing token']);
        }

        $token   = substr($header, 7);
        $decoded = JWT::for()->tryDecode($token);

        if ($decoded === null) {
            return service('response')->setStatusCode(401)
                ->setJSON(['error' => 'Invalid token']);
        }

        $request->jwt = $decoded;   // available downstream
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {}
}
```

Register and apply:

```php
// app/Config/Filters.php
public array $aliases = [
    'jwtAuth' => \App\Filters\JWTAuthFilter::class,
];

// app/Config/Routes.php
$routes->group('api', ['filter' => 'jwtAuth'], function ($routes) {
    $routes->get('profile', 'ProfileController::index');
});
```

---

## Multi-tenant / per-request configuration

`JWT` is immutable: the `with*()` methods (`withExpiresAt()`, `withLeeway()`, `withSplitData()`, `withParamData()`) each return a **new** instance, leaving the original — and the shared `config('JWT')` singleton — untouched. Prefer them over mutating config so concurrent requests can't trample each other's settings.

The `uid` passed to `encode()` may be a string **or** an integer (e.g. a DB primary key); `lcobucci/jwt` preserves the JSON type, so an integer `uid` round-trips back as an integer.

```php
use Daycry\JWT\JWT;

function issueAccessToken(array $payload, int|string $uid): string
{
    // Short-lived expiry overridden for this call only — config stays at its default.
    return JWT::for()
        ->withExpiresAt('+15 minutes')
        ->encode($payload, $uid);
}

function issueRefreshToken(int|string $uid): string
{
    return JWT::for()
        ->withExpiresAt('+30 days')
        ->encode(['type' => 'refresh'], $uid);
}
```

`withExpiresAt(string $modifier)` overrides the configured `expiresAt` for that instance only and accepts any `DateTimeImmutable::modify()` string. An empty string throws `InvalidArgumentException`, and an invalid modifier throws `InvalidArgumentException` when the token is encoded (consistently across PHP 8.2, which returns `false`, and 8.3+, which throws).

If you genuinely need a different *validation* profile per token type, pass a cloned config to the constructor — `$validateClaims` must always include `'SignedWith'` or `decode()` throws `JWTConfigurationException` (it refuses to silently skip signature verification):

```php
$config                 = clone config('JWT');
$config->validateClaims = ['SignedWith', 'LooseValidAt']; // keep SignedWith — required

$jwt = new JWT($config);
```

---

## Clock skew (`leeway`)

`LooseValidAt` and `StrictValidAt` accept a leeway. Use it when token issuers and verifiers run on machines with imperfect time sync.

```php
$config->leeway = 30;            // seconds; applies to iat / nbf / exp
// or per-call (returns a new instance, config untouched):
$jwt = JWT::for()->withLeeway(60);

// Reset to "no leeway" for a single instance:
$strict = JWT::for()->withLeeway(null);
```

`withLeeway(?int $seconds)` accepts a non-negative int or `null` (no leeway). A negative int throws `InvalidArgumentException`.

`StrictValidAt` requires `iat`, `nbf` and `exp` to be present **and** within the leeway window. `LooseValidAt` skips checks for any of the three that is missing.

---

## Performance notes

| Operation | Cost |
|---|---|
| `new JWT($config)` / `JWT::for()` | Negligible |
| First `encode()` / `decode()` | Builds the `Configuration` (signer + key load); `InMemory::file()` reads PEMs once per call |
| `tryDecode()` | Same as `decode()` plus a try/catch wrapper |
| `getPayload()` | `decode()` + a single `json_decode` |
| `isExpired()` / `getTimeToExpiry()` | Parse only — no validation |
| `extractClaimsUnsafe()` | Parse only + warning log |

The library deliberately does not cache validation constraints across calls. The benefit (a few microseconds per request) was not worth the v2.x bug it caused (a frozen clock validating expired tokens as fresh). Build the `JWT` instance on demand; instantiation is cheap.
