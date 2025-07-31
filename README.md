[![Donate](https://img.shields.io/badge/Donate-PayPal-green.svg)](https://www.paypal.com/donate?business=SYC5XDT23UZ5G&no_recurring=0&item_name=Thank+you%21&currency_code=EUR)

# JWT for CodeIgniter 4

A high-performance JWT (JSON Web Token) library for CodeIgniter 4, built on top of the robust `lcobucci/jwt` package.

[![Build Status](https://github.com/daycry/jwt/workflows/PHP%20Tests/badge.svg)](https://github.com/daycry/jwt/actions?query=workflow%3A%22PHP+Tests%22)
[![Coverage Status](https://coveralls.io/repos/github/daycry/jwt/badge.svg?branch=master)](https://coveralls.io/github/daycry/jwt?branch=master)
[![Downloads](https://poser.pugx.org/daycry/jwt/downloads)](https://packagist.org/packages/daycry/jwt)
[![GitHub release (latest by date)](https://img.shields.io/github/v/release/daycry/jwt)](https://packagist.org/packages/daycry/jwt)
[![GitHub stars](https://img.shields.io/github/stars/daycry/jwt)](https://packagist.org/packages/daycry/jwt)
[![GitHub license](https://img.shields.io/github/license/daycry/jwt)](https://github.com/daycry/jwt/blob/master/LICENSE)

## ✨ Features

- 🚀 **High Performance**: Lazy loading and constraint caching for optimal speed
- 🔒 **Secure**: Built on `lcobucci/jwt` with configurable validation constraints
- ⚡ **Fast Methods**: Quick validation and claim extraction without full decoding
- 🎛️ **Flexible Configuration**: Environment-aware settings and multiple algorithms
- 🧪 **Well Tested**: Comprehensive test suite with performance benchmarks
- 📱 **CI4 Integration**: Native CodeIgniter 4 integration with services and helpers

## 📋 Requirements

- PHP 8.1 or higher
- CodeIgniter 4.x
- `lcobucci/jwt ^4.0`

## 🚀 Installation

### Via Composer (Recommended)

```bash
composer require daycry/jwt
```

### Manual Installation

1. Download this repository
2. Add the namespace to **app/Config/Autoload.php**:

```php
$psr4 = [
    'Config'      => APPPATH . 'Config',
    APP_NAMESPACE => APPPATH,
    'App'         => APPPATH,
    'Daycry\JWT' => APPPATH .'ThirdParty/JWT/src',
];
```

## ⚙️ Configuration

### Publish Configuration File

```bash
php spark jwt:publish
```

This creates `app/Config/JWT.php` with all available options:

### Available CLI Commands

The library includes several helpful CLI commands:

```bash
# Publish configuration file to your app
php spark jwt:publish

# Generate a secure signing key
php spark jwt:key [length]

# Generate key with options
php spark jwt:key 32 --show      # Display key without updating .env
php spark jwt:key --force        # Force overwrite existing key
```

### Configuration File Structure

After publishing, you'll have `app/Config/JWT.php`:

```php
<?php

namespace Config;

use Daycry\JWT\Config\JWT as BaseJWT;

class JWT extends BaseJWT
{
    public ?string $uid = null;
    public string $signer = 'your-secret-key-base64';
    public string $issuer = 'https://your-domain.com';
    public string $audience = 'https://your-domain.com';
    public string $identifier = 'unique-app-id';
    public string $canOnlyBeUsedAfter = '+0 minute';
    public string $expiresAt = '+24 hour';
    public string $algorithm = \Lcobucci\JWT\Signer\Hmac\Sha256::class;
    public bool $throwable = true;
    public bool $validate = true;
    
    public array $validateClaims = [
        'SignedWith',
        'IssuedBy', 
        'ValidAt',
        'IdentifiedBy',
        'PermittedFor',
    ];
}
```

### Environment Variables Support

For security, use environment variables in your `.env` file. You can copy the provided example:

```bash
# Copy the example environment file
cp vendor/daycry/jwt/.env.example .env.jwt

# Or add these variables to your existing .env file:
```

```env
# JWT Configuration
jwt.signer=your-base64-encoded-secret-key
jwt.issuer=https://your-domain.com
jwt.audience=https://your-domain.com
jwt.identifier=your-unique-app-id
jwt.canOnlyBeUsedAfter="+0 minute"
jwt.expiresAt="+24 hour"
jwt.algorithm="Lcobucci\JWT\Signer\Hmac\Sha256"
jwt.throwable=true
jwt.validate=true
```
Then reference them in your configuration:

```php
<?php

namespace Config;

use Daycry\JWT\Config\JWT as BaseJWT;

class JWT extends BaseJWT
{
    public ?string $uid = null;
    public string $signer = 'your-secret-key-base64';
    public string $issuer = 'https://your-domain.com';
    public string $audience = 'https://your-domain.com';
    public string $identifier = 'unique-app-id';
    public string $canOnlyBeUsedAfter = '+0 minute';
    public string $expiresAt = '+24 hour';
    public string $algorithm = \Lcobucci\JWT\Signer\Hmac\Sha256::class;
    public bool $throwable = true;
    public bool $validate = true;
    
    public array $validateClaims = [
        'SignedWith',
        'IssuedBy', 
        'ValidAt',
        'IdentifiedBy',
        'PermittedFor',
    ];
}
```

> **Note**: CodeIgniter 4 automatically loads environment variables into configuration files, so no additional constructor is needed.

## 📚 Basic Usage

### Simple Token Creation and Validation

```php
<?php

// Create JWT instance
$jwt = new \Daycry\JWT\JWT();

// Encode data
$token = $jwt->encode(['user_id' => 123, 'role' => 'admin']);

// Decode and validate
$claims = $jwt->decode($token);
$userId = $claims->get('data'); // Default parameter name
```

### Custom Configuration

```php
<?php

$config = config('JWT');
$config->uid = 'user_123';
$jwt = new \Daycry\JWT\JWT($config);

$token = $jwt->encode(['action' => 'login']);
$claims = $jwt->decode($token);

echo $claims->get('uid'); // user_123
```

## 🎛️ Advanced Usage

### Custom Data Parameter

```php
<?php

$jwt = (new \Daycry\JWT\JWT())->setParamData('payload');
$token = $jwt->encode(['user' => 'john']);

$claims = $jwt->decode($token);
echo $claims->get('payload'); // JSON string of data
```

### Array Data Handling

#### Encoded as JSON (Default)
```php
<?php

$data = ['name' => 'John', 'role' => 'admin'];
$token = $jwt->encode($data);

$claims = $jwt->decode($token);
$originalData = json_decode($claims->get('data'), true);
```

#### Split as Individual Claims
```php
<?php

$jwt->setSplitData(true);
$data = ['name' => 'John', 'role' => 'admin'];
$token = $jwt->encode($data);

$claims = $jwt->decode($token);
echo $claims->get('name'); // John
echo $claims->get('role'); // admin
```

## ⚡ High-Performance Methods

### Quick Validation (No Full Decoding)

```php
<?php

$jwt = new \Daycry\JWT\JWT();
$token = $jwt->encode(['user_id' => 123]);

// Fast validation without full decoding
if ($jwt->isValid($token)) {
    echo "Token is valid!";
}
```

### Unsafe Claim Extraction (Performance Critical)

```php
<?php

// Extract claims without validation (2x faster)
$claims = $jwt->extractClaimsUnsafe($token);
$userId = $claims['uid'] ?? null;

// Use when you trust the token source
```

### Expiry Checking

```php
<?php

// Quick expiry check
if ($jwt->isExpired($token)) {
    return response('Token expired', 401);
}

// Get time to expiry in seconds
$timeLeft = $jwt->getTimeToExpiry($token);
if ($timeLeft < 300) { // Less than 5 minutes
    // Trigger refresh logic
}
```

## 🔧 Configuration Options

### Validation Constraints

You can customize which constraints to validate:

```php
<?php

$config = config('JWT');

// All constraints (default)
$config->validateClaims = [
    'SignedWith',   // Verify signature
    'IssuedBy',     // Verify issuer
    'ValidAt',      // Verify time constraints
    'IdentifiedBy', // Verify token ID
    'PermittedFor', // Verify audience
];

// Minimal validation (performance focused)
$config->validateClaims = ['SignedWith'];

// Disable validation entirely (not recommended for production)
$config->validate = false;
```

### Supported Algorithms

```php
<?php

// Symmetric algorithms (recommended)
$config->algorithm = \Lcobucci\JWT\Signer\Hmac\Sha256::class; // Default
$config->algorithm = \Lcobucci\JWT\Signer\Hmac\Sha384::class;
$config->algorithm = \Lcobucci\JWT\Signer\Hmac\Sha512::class;
```

## 🛠️ Integration Features

### CodeIgniter 4 Services

```php
<?php

// In your controller or anywhere in CI4
$jwt = service('jwt'); // If you implement the Services.php
$token = $jwt->encode(['user_id' => auth()->id()]);
```

### Helper Functions (If Implemented)

```php
<?php

// Quick encoding
$token = jwt_encode(['user_id' => 123]);

// Quick decoding
$claims = jwt_decode($token);

// Current user ID from JWT
$userId = jwt_user_id();

// Check if user is authenticated
if (jwt_check()) {
    // User has valid JWT
}
```

## 🧪 Testing and Benchmarks

### Run Tests

```bash
composer test
```

### Performance Benchmark

```bash
php benchmark.php
```

Sample benchmark results:
```
🚀 JWT Performance Benchmark
==================================================
📦 Object Creation (Lazy Loading): 0.000002s avg
🔐 Token Encoding: 0.000089s avg  
🔓 Token Decoding (cached): 0.000089s avg
⚡ Fast Validation: 0.000085s avg
🔥 Unsafe Extraction: 0.000041s avg (2x faster)
⏳ Expiry Check: 0.000045s avg
```

## 🔒 Security Best Practices

### 1. Use Strong Secret Keys

```bash
# Generate a secure key using the built-in command
php spark jwt:key

# Generate with custom length (32 bytes = 256 bits)
php spark jwt:key 32

# Just display the key without updating .env
php spark jwt:key --show

# Alternative: Generate using OpenSSL
openssl rand -base64 32

# Or using PHP directly
php -r "echo base64_encode(random_bytes(32)) . PHP_EOL;"
```

The `jwt:key` command will automatically add the key to your `.env` file:

```env
# JWT Configuration  
jwt.signer=generated-base64-key-here
```

**Important**: Never commit your secret keys to version control. Always use environment variables.

### 2. Configure Appropriate Expiry Times

```php
<?php

// Short-lived tokens for APIs
$config->expiresAt = '+15 minutes';

// Longer-lived for web sessions
$config->expiresAt = '+2 hours';
```

### 3. Validate All Necessary Claims

```php
<?php

// Production configuration
$config->validate = true;
$config->validateClaims = [
    'SignedWith',
    'IssuedBy',
    'ValidAt',
    'IdentifiedBy',
    'PermittedFor',
];
```

## 🚨 Error Handling

### With Exceptions (Default)

```php
<?php

try {
    $claims = $jwt->decode($token);
    // Token is valid
} catch (\Lcobucci\JWT\Validation\RequiredConstraintsViolated $e) {
    // Token validation failed
    return response('Invalid token', 401);
}
```

### Without Exceptions

```php
<?php

$config = config('JWT');
$config->throwable = false;
$jwt = new \Daycry\JWT\JWT($config);

$result = $jwt->decode($token);

if ($result instanceof \Lcobucci\JWT\Validation\RequiredConstraintsViolated) {
    // Handle validation error
    echo $result->getMessage();
} else {
    // Valid claims
    $userId = $result->get('uid');
}
```

## 📖 API Reference

### Main Methods

| Method | Description | Performance |
|--------|-------------|-------------|
| `encode($data, $uid = null)` | Create JWT token | Standard |
| `decode($token)` | Validate and decode token | Standard |
| `isValid($token)` | Quick validation check | Fast |
| `extractClaimsUnsafe($token)` | Extract without validation | Fastest |
| `isExpired($token)` | Check if token expired | Fast |
| `getTimeToExpiry($token)` | Get seconds until expiry | Fast |
| `clearCache()` | Clear constraint cache | Instant |

### Configuration Methods

| Method | Description |
|--------|-------------|
| `setParamData($name)` | Set custom data parameter name |
| `setSplitData($enabled)` | Enable/disable claim splitting |
| `getParamData()` | Get current data parameter name |

## 🤝 Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- Built on top of the excellent [`lcobucci/jwt`](https://github.com/lcobucci/jwt) library
- Inspired by the CodeIgniter 4 community
- Thanks to all contributors and users

## 💬 Support

- 📫 Create an issue for bug reports or feature requests
- 💰 [Donate via PayPal](https://www.paypal.com/donate?business=SYC5XDT23UZ5G&no_recurring=0&item_name=Thank+you%21&currency_code=EUR) to support development
