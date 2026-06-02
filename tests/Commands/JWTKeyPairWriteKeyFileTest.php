<?php

declare(strict_types=1);

namespace Tests\Commands;

use CodeIgniter\CLI\Commands;
use CodeIgniter\Log\Logger;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\StreamFilterTrait;
use Config\Logger as LoggerConfig;
use Daycry\JWT\Commands\JWTKeyPair;
use ReflectionMethod;
use Throwable;

/**
 * Exercises JWTKeyPair::writeKeyFile() in isolation. Unlike the full command
 * tests this needs no OpenSSL, so it runs on Windows too — which is exactly the
 * platform where the chmod() no-op warning matters.
 *
 * @internal
 */
final class JWTKeyPairWriteKeyFileTest extends CIUnitTestCase
{
    use StreamFilterTrait;

    private ?string $tmpDir = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'jwt-writekey-test-' . uniqid();
        mkdir($this->tmpDir, 0700, true);
    }

    protected function tearDown(): void
    {
        if ($this->tmpDir !== null && is_dir($this->tmpDir)) {
            $files = glob($this->tmpDir . '/*');
            if (is_array($files)) {
                foreach ($files as $file) {
                    @unlink($file);
                }
            }
            @rmdir($this->tmpDir);
        }

        parent::tearDown();
    }

    private function invokeWriteKeyFile(string $path, string $contents, int $mode): string
    {
        $this->resetStreamFilterBuffer();

        $command = new JWTKeyPair(new Logger(new LoggerConfig()), new Commands());
        $method  = new ReflectionMethod(JWTKeyPair::class, 'writeKeyFile');
        $method->invoke($command, $path, $contents, $mode);

        return $this->getStreamFilterBuffer();
    }

    public function testWriteKeyFileStoresContents(): void
    {
        $path = $this->tmpDir . DIRECTORY_SEPARATOR . 'sample-private.pem';

        $this->invokeWriteKeyFile($path, 'PEM-CONTENTS', 0600);

        $this->assertFileExists($path);
        $this->assertSame('PEM-CONTENTS', file_get_contents($path));
    }

    public function testWriteKeyFileWarnsAboutPermissionsOnWindows(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('The chmod() permission warning only applies on Windows.');
        }

        $path   = $this->tmpDir . DIRECTORY_SEPARATOR . 'win-private.pem';
        $output = $this->invokeWriteKeyFile($path, 'PEM', 0600);

        $this->assertStringContainsString('NTFS', $output);
    }

    public function testWriteKeyFileAppliesModeOnPosix(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('POSIX permission bits are not enforced on Windows.');
        }

        $path = $this->tmpDir . DIRECTORY_SEPARATOR . 'posix-private.pem';
        $this->invokeWriteKeyFile($path, 'PEM', 0600);

        $this->assertSame('0600', substr(sprintf('%o', fileperms($path)), -4));
    }

    public function testWriteKeyFileSurfacesWriteFailures(): void
    {
        // Writing into a non-existent directory must not silently succeed: either our
        // own guard (RuntimeException) or PHP's warning-to-exception conversion fires.
        $path = $this->tmpDir . DIRECTORY_SEPARATOR . 'missing-dir' . DIRECTORY_SEPARATOR . 'x.pem';

        $this->expectException(Throwable::class);
        $this->invokeWriteKeyFile($path, 'PEM', 0600);
    }
}
