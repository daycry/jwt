# CLI Commands

> **[← Back to index](index.md)**

The library registers three Spark commands under the `JWT` group.

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
Generated key: <random-base64-string>
⚠️  Keep your .env file secure and never commit it to version control!
```

**Display only (no file I/O):**

```bash
php spark jwt:key --show
```

```
Generated JWT Key (32 bytes):
<random-base64-string>

Add this to your .env file:
jwt.signer=<random-base64-string>

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

---

## jwt:keypair

```
php spark jwt:keypair [--algorithm=rsa|ecdsa] [--bits=2048] [--curve=prime256v1]
                      [--output=writable/keys] [--name=jwt] [--passphrase=…] [--force]
```

Generates an RSA or ECDSA key pair on disk and prints the `.env` snippet to copy.

### Options

| Option | Default | Description |
|---|---|---|
| `--algorithm` | `rsa` | `rsa` or `ecdsa`. |
| `--bits` | `2048` | RSA key size. Minimum 2048. Ignored for ECDSA. |
| `--curve` | `prime256v1` | ECDSA curve. Common: `prime256v1`, `secp384r1`, `secp521r1`. Ignored for RSA. |
| `--output` | `writable/keys` | Directory for the generated `.pem` files. Created if missing (mode `0700`). |
| `--name` | `jwt` | Base file name. Result: `<name>-private.pem` + `<name>-public.pem`. |
| `--passphrase` | *(none)* | Encrypt the private key with this passphrase. Don't forget to also set `Config\JWT::$passphrase`. |
| `--force` | *(absent)* | Overwrite existing files at the target path. Without it the command refuses to clobber. |

### Examples

```bash
# Default RSA-2048 keypair into writable/keys
php spark jwt:keypair

# ECDSA-P256 (ES256)
php spark jwt:keypair --algorithm=ecdsa --curve=prime256v1

# RSA-4096 with passphrase, custom location
php spark jwt:keypair --algorithm=rsa --bits=4096 \
                     --output=/srv/secrets/jwt --name=app1 \
                     --passphrase='something-strong'
```

The command prints the exact `.env` keys you need to set afterwards. The private file is `chmod 0600`, the public file `chmod 0644`.

> ⚠️ Never commit the private key. Store it outside the repository (e.g. CI secret store, vault) when possible.
