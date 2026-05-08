<?php

namespace Tests\Commands;

use CodeIgniter\CLI\CLI;
use CodeIgniter\CLI\Commands;
use CodeIgniter\Log\Logger;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\StreamFilterTrait;
use Config\Logger as LoggerConfig;
use Daycry\JWT\Commands\JWTKeyPair;
use ReflectionProperty;

/**
 * @internal
 */
final class JWTKeyPairTest extends CIUnitTestCase
{
    use StreamFilterTrait;

    /** @var mixed[] */
    private array $originalOptions = [];
    private ?string $tmpDir        = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (! self::opensslIsUsable()) {
            $this->markTestSkipped('OpenSSL pkey functions are not available (missing openssl.cnf?).');
        }

        $reflection            = new ReflectionProperty(CLI::class, 'options');
        $this->originalOptions = $reflection->getValue();

        $this->tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'jwt-keypair-test-' . uniqid();
    }

    private static function opensslIsUsable(): bool
    {
        $resource = @openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ]);
        if ($resource === false) {
            // Drain pending OpenSSL errors so they do not leak into other tests.
            while (openssl_error_string() !== false) {
            }
            return false;
        }

        return true;
    }

    protected function tearDown(): void
    {
        $reflection = new ReflectionProperty(CLI::class, 'options');
        $reflection->setValue(null, $this->originalOptions);

        if ($this->tmpDir !== null && is_dir($this->tmpDir)) {
            foreach (glob($this->tmpDir . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($this->tmpDir);
        }

        parent::tearDown();
    }

    /**
     * @param mixed[] $options
     */
    private function setCliOptions(array $options): void
    {
        $reflection = new ReflectionProperty(CLI::class, 'options');
        $reflection->setValue(null, $options);
    }

    private function runCommand(array $options): string
    {
        $this->setCliOptions($options);
        $this->resetStreamFilterBuffer();

        $command = new JWTKeyPair(new Logger(new LoggerConfig()), new Commands());
        $command->run([]);

        return $this->getStreamFilterBuffer();
    }

    public function testRsaKeyPairGeneration(): void
    {
        $output = $this->runCommand([
            'algorithm' => 'rsa',
            'bits'      => '2048',
            'output'    => $this->tmpDir,
            'name'      => 'rsa-test',
        ]);

        $this->assertStringContainsString('Key pair generated', $output);
        $this->assertFileExists($this->tmpDir . '/rsa-test-private.pem');
        $this->assertFileExists($this->tmpDir . '/rsa-test-public.pem');

        $private = file_get_contents($this->tmpDir . '/rsa-test-private.pem');
        $public  = file_get_contents($this->tmpDir . '/rsa-test-public.pem');

        $this->assertStringContainsString('-----BEGIN PRIVATE KEY-----', (string) $private);
        $this->assertStringContainsString('-----BEGIN PUBLIC KEY-----', (string) $public);
    }

    public function testEcdsaKeyPairGeneration(): void
    {
        $output = $this->runCommand([
            'algorithm' => 'ecdsa',
            'curve'     => 'prime256v1',
            'output'    => $this->tmpDir,
            'name'      => 'ecdsa-test',
        ]);

        $this->assertStringContainsString('Key pair generated', $output);
        $this->assertFileExists($this->tmpDir . '/ecdsa-test-private.pem');
        $this->assertFileExists($this->tmpDir . '/ecdsa-test-public.pem');

        $public = (string) file_get_contents($this->tmpDir . '/ecdsa-test-public.pem');
        $this->assertStringContainsString('-----BEGIN PUBLIC KEY-----', $public);
    }

    public function testRefusesToOverwriteWithoutForce(): void
    {
        // First run creates the keys.
        $this->runCommand([
            'algorithm' => 'rsa',
            'bits'      => '2048',
            'output'    => $this->tmpDir,
            'name'      => 'collision',
        ]);

        // Second run without --force must refuse and not delete the files.
        $output = $this->runCommand([
            'algorithm' => 'rsa',
            'bits'      => '2048',
            'output'    => $this->tmpDir,
            'name'      => 'collision',
        ]);

        $this->assertStringContainsString('already exist', $output);
        $this->assertFileExists($this->tmpDir . '/collision-private.pem');
    }

    public function testForceOverwritesExisting(): void
    {
        $this->runCommand([
            'algorithm' => 'rsa',
            'bits'      => '2048',
            'output'    => $this->tmpDir,
            'name'      => 'overwrite',
        ]);

        $original = file_get_contents($this->tmpDir . '/overwrite-private.pem');

        $this->runCommand([
            'algorithm' => 'rsa',
            'bits'      => '2048',
            'output'    => $this->tmpDir,
            'name'      => 'overwrite',
            'force'     => null,
        ]);

        $regenerated = file_get_contents($this->tmpDir . '/overwrite-private.pem');

        $this->assertNotSame($original, $regenerated, 'Force flag should regenerate the key');
    }
}
