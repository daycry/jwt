<?php

declare(strict_types=1);

namespace Tests\Validators;

use CodeIgniter\Test\CIUnitTestCase;
use Daycry\JWT\Config\JWT as JWTConfig;
use Daycry\JWT\JWT;
use Lcobucci\JWT\Signer\Ecdsa\Sha256 as EcdsaSha256;
use Lcobucci\JWT\Signer\Rsa\Sha256 as RsaSha256;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Validation\RequiredConstraintsViolated;

/**
 * @internal
 */
final class AsymmetricTest extends CIUnitTestCase
{
    private ?string $tmpDir = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->opensslIsUsable()) {
            $this->markTestSkipped('OpenSSL pkey functions are not available (missing openssl.cnf?).');
        }

        $this->tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'jwt-asym-' . uniqid();
        mkdir($this->tmpDir, 0700, true);
    }

    private function opensslIsUsable(): bool
    {
        $resource = @openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ]);
        if ($resource === false) {
            while (openssl_error_string() !== false) {
            }

            return false;
        }

        return true;
    }

    protected function tearDown(): void
    {
        if ($this->tmpDir !== null && is_dir($this->tmpDir)) {
            foreach (glob($this->tmpDir . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($this->tmpDir);
        }

        parent::tearDown();
    }

    private function generateRsaKeyPair(string $name = 'rsa'): array
    {
        $resource = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ]);
        openssl_pkey_export($resource, $privatePem);
        $publicPem = openssl_pkey_get_details($resource)['key'];

        $unique      = $name . '-' . uniqid('', true);
        $privatePath = $this->tmpDir . DIRECTORY_SEPARATOR . $unique . '-private.pem';
        $publicPath  = $this->tmpDir . DIRECTORY_SEPARATOR . $unique . '-public.pem';
        file_put_contents($privatePath, $privatePem);
        file_put_contents($publicPath, $publicPem);

        return [$privatePath, $publicPath];
    }

    private function generateEcdsaKeyPair(string $name = 'ec'): array
    {
        $resource = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name'       => 'prime256v1',
        ]);
        openssl_pkey_export($resource, $privatePem);
        $publicPem = openssl_pkey_get_details($resource)['key'];

        $unique      = $name . '-' . uniqid('', true);
        $privatePath = $this->tmpDir . DIRECTORY_SEPARATOR . $unique . '-private.pem';
        $publicPath  = $this->tmpDir . DIRECTORY_SEPARATOR . $unique . '-public.pem';
        file_put_contents($privatePath, $privatePem);
        file_put_contents($publicPath, $publicPem);

        return [$privatePath, $publicPath];
    }

    private function buildAsymmetricConfig(string $algorithm, string $signingKey, string $verifyingKey): JWTConfig
    {
        $config                = new JWTConfig();
        $config->algorithmType = 'asymmetric';
        $config->algorithm     = $algorithm;
        $config->signingKey    = $signingKey;
        $config->verifyingKey  = $verifyingKey;
        $config->issuer        = 'https://test.example.com';
        $config->audience      = 'https://test.example.com';
        $config->identifier    = 'asym-test';

        return $config;
    }

    public function testRsaRoundTripWithFilePaths(): void
    {
        [$private, $public] = $this->generateRsaKeyPair();
        $config             = $this->buildAsymmetricConfig(RsaSha256::class, $private, $public);
        $jwt                = new JWT($config);

        $token   = $jwt->encode(['scope' => 'admin']);
        $decoded = $jwt->decode($token);

        $this->assertInstanceOf(Plain::class, $decoded);
        $payload = json_decode((string) $decoded->claims()->get('data'), true);
        $this->assertSame('admin', $payload['scope']);
    }

    public function testEcdsaRoundTrip(): void
    {
        [$private, $public] = $this->generateEcdsaKeyPair();
        $config             = $this->buildAsymmetricConfig(EcdsaSha256::class, $private, $public);
        $jwt                = new JWT($config);

        $token   = $jwt->encode('admin');
        $decoded = $jwt->decode($token);

        $this->assertSame('admin', $decoded->claims()->get('data'));
    }

    public function testRsaTokenSignedWithDifferentKeyFails(): void
    {
        [$privateA, $publicA] = $this->generateRsaKeyPair();
        [, $publicB]          = $this->generateRsaKeyPair();

        $signerConfig = $this->buildAsymmetricConfig(RsaSha256::class, $privateA, $publicA);
        $token        = (new JWT($signerConfig))->encode('admin');

        // Verifier expects a different public key.
        $verifierConfig = $this->buildAsymmetricConfig(RsaSha256::class, $privateA, $publicB);

        $this->expectException(RequiredConstraintsViolated::class);
        (new JWT($verifierConfig))->decode($token);
    }
}
