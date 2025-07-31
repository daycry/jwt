<?php

namespace Daycry\JWT\Config;

use CodeIgniter\Config\BaseConfig;
use Lcobucci\JWT\Signer\Hmac\Sha256;

class JWT extends BaseConfig
{
    /**
     * UID field for Bearer Token
     * You can set with dinamically values passing as second parameter in encode function
     * ex: 'myApp'
     */
    public ?string $uid = null;

    /**
     * In Base64 encode
     */
    public string $signer = 'mBC5v1sOKVvbdEitdSBenu59nfNfhwkedkJVNabosTw=';

    public string $issuer             = 'http://example.local';
    public string $audience           = 'http://example.local';
    public string $identifier         = '4f1g23a12aa';
    public string $canOnlyBeUsedAfter = '+0 minute';
    public string $expiresAt          = '+24 hour';

    /**
     * Symetric Algorithms
     *
     * Options:
     *
     * \Lcobucci\JWT\Signer\Hmac\Sha256::class
     * \Lcobucci\JWT\Signer\Hmac\Sha384::class
     * \Lcobucci\JWT\Signer\Hmac\Sha512::class
     */
    public string $algorithm = Sha256::class;

    public bool $throwable = true;
    public bool $validate  = true;

    /**
     * Array of constraint class names to validate claims
     * These will be instantiated dynamically with proper parameters
     */
    public array $validateClaims = [
        'SignedWith',
        'IssuedBy',
        'ValidAt',
        'IdentifiedBy',
        'PermittedFor',
    ];
}
