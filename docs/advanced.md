# Advanced

> **[← Back to index](index.md)**

---

## Utility Methods

Beyond the core `encode()` / `decode()` pair, the `JWT` class exposes several convenience methods for scenarios where you need lightweight checks without full token decoding.

---

### isValid()

```php
public function isValid(string $token): bool
```

Returns `true` if the token can be parsed **and** passes all configured validation constraints. Returns `false` on any parsing or validation error — never throws.

```php
if (!$jwt->isValid($bearerToken)) {
    return $this->response->setStatusCode(401);
}
```

Internally reuses the same constraint cache as `decode()`, so there is no double overhead when both methods are called.

---

### isExpired()

```php
public function isExpired(string $token): bool
```

Checks only the `exp` claim against the current Unix timestamp. Does **not** verify the signature or any other constraint.

| Return | Meaning |
|---|---|
| `false` | Token is not expired (or has no `exp` claim) |
| `true` | Token is expired, or the token string could not be parsed |

```php
if ($jwt->isExpired($token)) {
    // redirect to token refresh flow
}
```

> This is intentionally a low-cost check. Use it in scenarios where you have already verified the signature elsewhere and only need to know if the token is still within its lifetime.

---

### getTimeToExpiry()

```php
public function getTimeToExpiry(string $token): ?int
```

Returns the number of seconds until the token expires.

| Return | Meaning |
|---|---|
| `int ≥ 0` | Seconds remaining (0 means expired or expires right now) |
| `null` | Token has no `exp` claim, or could not be parsed |

```php
$ttl = $jwt->getTimeToExpiry($token);

if ($ttl !== null && $ttl < 300) {
    // Warn client to refresh within the next 5 minutes
}
```

---

### extractClaimsUnsafe()

```php
public function extractClaimsUnsafe(string $token): ?array
```

Parses the token and returns all claims as an associative array **without any validation**. Returns `null` if the token string cannot be parsed.

```php
$claims = $jwt->extractClaimsUnsafe($token);

if ($claims !== null) {
    $userId = $claims['uid'] ?? null;
}
```

> **Important:** Use this method only when you have already validated the token through another mechanism, or when you intentionally need to inspect an untrusted token (e.g. to read the issuer before deciding which key to use for verification).

---

### clearCache()

```php
public function clearCache(): void
```

Empties the internal constraint cache. Useful when you change `$config->validateClaims` on an existing `JWT` instance and need to rebuild the constraints.

```php
$config->validateClaims = ['SignedWith', 'ValidAt'];
$jwt->clearCache();
$jwt->decode($token); // constraints rebuilt from the new list
```

> In normal usage you do not need to call this. Passing a new `JWTConfig` instance to the constructor is the preferred approach.

---

## Error Handling

### Exception: `RequiredConstraintsViolated`

All validation failures produce a `Lcobucci\JWT\Validation\RequiredConstraintsViolated` exception. It implements `getMessage()` which returns a human-readable list of the violated constraints.

```php
use Lcobucci\JWT\Validation\RequiredConstraintsViolated;

try {
    $claims = $jwt->decode($token);
    // use $claims
} catch (RequiredConstraintsViolated $e) {
    log_message('warning', 'JWT validation failed: ' . $e->getMessage());
    return $this->respond(['error' => 'Invalid token'], 401);
}
```

### Exception: `\JsonException`

`encode()` uses `JSON_THROW_ON_ERROR` when serialising array payloads. If the data contains non-serialisable values (e.g. `resource` types), a `\JsonException` is thrown.

```php
try {
    $token = $jwt->encode($payload);
} catch (\JsonException $e) {
    // payload could not be serialised
}
```

### Soft errors with `throwable = false`

When you prefer to avoid exceptions entirely, set `$config->throwable = false`. In this mode, `decode()` returns the exception object rather than throwing it:

```php
$config->throwable = false;
$result = $jwt->decode($token);

if ($result instanceof RequiredConstraintsViolated) {
    // handle the error
} else {
    echo $result->get('data');
}
```

---

## Middleware Pattern

A common pattern is to build a CodeIgniter 4 filter that authenticates requests via the `Authorization` header:

```php
<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Daycry\JWT\JWT;
use Lcobucci\JWT\Validation\RequiredConstraintsViolated;

class JWTAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $header = $request->getHeaderLine('Authorization');

        if (!str_starts_with($header, 'Bearer ')) {
            return service('response')->setStatusCode(401)
                ->setJSON(['error' => 'Missing token']);
        }

        $token = substr($header, 7);
        $jwt   = new JWT();

        try {
            $claims = $jwt->decode($token);
            // Store claims for use in the controller
            $request->claims = $claims;
        } catch (RequiredConstraintsViolated) {
            return service('response')->setStatusCode(401)
                ->setJSON(['error' => 'Invalid token']);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {}
}
```

Register it in `app/Config/Filters.php`:

```php
public array $aliases = [
    'jwtAuth' => \App\Filters\JWTAuthFilter::class,
];
```

Apply to routes:

```php
// app/Config/Routes.php
$routes->group('api', ['filter' => 'jwtAuth'], function($routes) {
    $routes->get('profile', 'ProfileController::index');
});
```

---

## Multi-Tenant / Per-Request Configuration

For applications that issue tokens to multiple audiences with different expiry settings, instantiate a `JWT` object per context rather than sharing a single instance:

```php
function issueShortLivedToken(array $data, string $uid): string
{
    $config            = config('JWT');
    $config->expiresAt = '+15 minutes';
    $config->uid       = $uid;
    return (new JWT($config))->encode($data);
}

function issueLongLivedRefreshToken(string $uid): string
{
    $config            = config('JWT');
    $config->expiresAt = '+30 days';
    $config->uid       = $uid;
    // Narrow constraints: only check signature and exp
    $config->validateClaims = ['SignedWith', 'ValidAt'];
    return (new JWT($config))->encode(['type' => 'refresh']);
}
```

---

## Performance Notes

| Operation | Cost | Detail |
|---|---|---|
| `new JWT()` | Negligible | `lcobucci/jwt` `Configuration` is not built until first use |
| First `encode()` | Medium | Builds and caches the `Configuration` object |
| Subsequent `encode()` | Low | Reuses cached `Configuration` |
| First `decode()` | Medium | Builds and caches the constraint list |
| Subsequent `decode()` | Low | Reuses cached constraints |
| `isValid()` | Same as `decode()` | Shares the same constraint cache |
| `isExpired()` | Very low | Parses only the `exp` claim |
| `extractClaimsUnsafe()` | Very low | Parses token, skips constraint check |
