# CLI Commands

> **[← Back to index](index.md)**

The library registers two Spark commands under the `JWT` group.

---

## jwt:publish

```
php spark jwt:publish
```

Publishes the package's default configuration file into your application.

### What it does

1. Reads `vendor/daycry/jwt/src/Config/JWT.php`.
2. Replaces the namespace from `Daycry\JWT\Config` → `Config`.
3. Changes the parent class from `extends BaseConfig` → `extends \Daycry\JWT\Config\JWT` so your app config inherits all defaults and you only need to override what you change.
4. Writes the result to `app/Config/JWT.php`.

### Output

```
Created: Config/JWT.php
Config file was successfully generated.
```

If `app/Config/JWT.php` already exists, you will be prompted:

```
Config file already exists, do you want to replace it? [y, n]:
```

### Published file structure

```php
<?php

namespace Config;

use Daycry\JWT\Config\JWT as BaseJWT;

class JWT extends BaseJWT
{
    // Override only what you need.
    // All properties are inherited from BaseJWT.
}
```

---

## jwt:key

```
php spark jwt:key [length] [--show] [--force]
```

Generates a cryptographically secure, Base64-encoded signing key using `random_bytes()`.

### Arguments

| Argument | Type | Default | Description |
|---|---|---|---|
| `length` | `int` | `32` | Key length **in bytes** before Base64 encoding. |

### Options

| Option | Description |
|---|---|
| `--show` | Print the key to the terminal without touching the `.env` file. |
| `--force` | Overwrite an existing `jwt.signer` entry in `.env` without prompting. |

### Constraints on `length`

| Condition | Result |
|---|---|
| `length < 16` | Error: `Key length must be at least 16 bytes for security` |
| `length > 128` | Error: `Key length cannot exceed 128 bytes` |
| `16 ≤ length ≤ 128` | Key generated successfully |

### Resulting Base64 lengths

| Bytes | Base64 chars |
|---|---|
| 16 | 24 |
| 32 (default) | 44 |
| 64 | 88 |
| 128 | 172 |

### Examples

**Default — generate a 32-byte key and write to `.env`:**

```bash
php spark jwt:key
```

```
✅ JWT key successfully added to .env file
Generated key: mBC5v1sOKVvbdEitdSBenu59nfNfhwkedkJVNabosTw=
⚠️  Keep your .env file secure and never commit it to version control!
```

**Display only (no file I/O):**

```bash
php spark jwt:key --show
```

```
Generated JWT Key (32 bytes):
mBC5v1sOKVvbdEitdSBenu59nfNfhwkedkJVNabosTw=

Add this to your .env file:
jwt.signer=mBC5v1sOKVvbdEitdSBenu59nfNfhwkedkJVNabosTw=

⚠️  Keep this key secure and never commit it to version control!
```

**Custom length:**

```bash
php spark jwt:key 64
```

**Force overwrite an existing key:**

```bash
php spark jwt:key --force
```

### `.env` behaviour

| Scenario | Result |
|---|---|
| `.env` does not exist | Error message with hint to create the file |
| `jwt.signer` not present | `jwt.signer=<newkey>` appended under a `# JWT Configuration` comment |
| `jwt.signer` already present, no `--force` | Interactive prompt: overwrite? |
| `jwt.signer` already present + `--force` | Silent overwrite |
| `CI_ENVIRONMENT=testing` or `APP_ENV=testing` | Silent overwrite (test-safe behaviour) |
