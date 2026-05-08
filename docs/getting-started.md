# Getting Started

> **[← Back to index](index.md)**

---

## Requirements

- PHP **8.2** or higher
- CodeIgniter **4.x**
- Composer

---

## Installation

```bash
composer require daycry/jwt
```

---

## Initial Setup

### 1. Publish the configuration file

```bash
php spark jwt:publish
```

This copies the default config into your application at `app/Config/JWT.php`.

### 2. Generate a secure signing key

```bash
php spark jwt:key
```

The key is written automatically to your `.env` file as `jwt.signer`. Alternatively, display it without writing to disk:

```bash
php spark jwt:key --show
```

### 3. Verify the `.env` entry

```ini
jwt.signer = "<paste-the-key-generated-above-here>"
```

> ⚠️ The library refuses to encode or decode tokens until `jwt.signer` is set
> to a non-empty base64 string. **Never commit your signing key to version
> control.** Add `.env` to `.gitignore`.

---

## Quick Example

```php
<?php

use Daycry\JWT\JWT;

// --- Encoding ---
$jwt   = new JWT();
$token = $jwt->encode('hello world');   // scalar payload
// $token = $jwt->encode(['user_id' => 1, 'role' => 'admin'], 'user-1');

// --- Decoding ---
$claims = $jwt->decode($token);

echo $claims->get('data');   // "hello world"
echo $claims->get('uid');    // value from config or second encode() argument
```

---

## Dependency Injection

You can inject a custom config object instead of relying on `config('JWT')`:

```php
use Daycry\JWT\JWT;
use Daycry\JWT\Config\JWT as JWTConfig;

$config            = new JWTConfig();
$config->issuer    = 'https://my-app.com';
$config->expiresAt = '+1 hour';

$jwt = new JWT($config);
```

This pattern is especially useful in tests and service containers.

---

## Next Steps

| Topic | Document |
|---|---|
| All configuration options | [Configuration](configuration.md) |
| encode / decode / validate | [Usage](usage.md) |
| CLI commands reference | [CLI Commands](commands.md) |
| Utility methods & error handling | [Advanced](advanced.md) |
| Running the test suite | [Testing](testing.md) |
