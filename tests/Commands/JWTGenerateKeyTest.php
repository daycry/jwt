<?php

namespace Tests\Commands;

use CodeIgniter\CLI\CLI;
use CodeIgniter\CLI\Commands;
use CodeIgniter\Log\Logger;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\StreamFilterTrait;
use Config\Logger as LoggerConfig;
use Daycry\JWT\Commands\JWTGenerateKey;
use ReflectionClass;
use ReflectionProperty;

/**
 * @internal
 */
final class JWTGenerateKeyTest extends CIUnitTestCase
{
    use StreamFilterTrait;

    /** @var mixed[] */
    private array $originalOptions = [];

    protected function setUp(): void
    {
        parent::setUp();

        $reflection            = new ReflectionProperty(CLI::class, 'options');
        $this->originalOptions = $reflection->getValue();
    }

    protected function tearDown(): void
    {
        $reflection = new ReflectionProperty(CLI::class, 'options');
        $reflection->setValue(null, $this->originalOptions);

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

    /**
     * @param string[] $params
     * @param mixed[]  $options
     */
    private function runCommand(array $params, array $options = []): string
    {
        $this->setCliOptions($options);
        $this->resetStreamFilterBuffer();

        $command = new JWTGenerateKey(new Logger(new LoggerConfig()), new Commands());
        $command->run($params);

        return $this->getStreamFilterBuffer();
    }

    public function testShowOptionGeneratesValidBase64Key(): void
    {
        $output = $this->runCommand(['32'], ['show' => null]);

        $this->assertStringContainsString('Generated JWT Key (32 bytes)', $output);
        $this->assertMatchesRegularExpression('#[A-Za-z0-9+/]{40,}={0,2}#', $output);
    }

    public function testDefaultLengthIs32Bytes(): void
    {
        $output = $this->runCommand([], ['show' => null]);

        $this->assertStringContainsString('Generated JWT Key (32 bytes)', $output);
    }

    public function testRejectsLengthBelowMinimum(): void
    {
        $output = $this->runCommand(['8'], ['show' => null]);

        $this->assertStringContainsString('at least 16 bytes', $output);
    }

    public function testRejectsLengthAboveMaximum(): void
    {
        $output = $this->runCommand(['256'], ['show' => null]);

        $this->assertStringContainsString('cannot exceed 128 bytes', $output);
    }

    public function testCustomLengthIsRespected(): void
    {
        $output = $this->runCommand(['64'], ['show' => null]);

        $this->assertStringContainsString('Generated JWT Key (64 bytes)', $output);
    }

    public function testCommandMetadata(): void
    {
        $reflection = new ReflectionClass(JWTGenerateKey::class);
        $properties = $reflection->getDefaultProperties();

        $this->assertSame('JWT', $properties['group']);
        $this->assertSame('jwt:key', $properties['name']);
        $this->assertStringContainsString('Generate a secure JWT signing key', $properties['description']);
        $this->assertArrayHasKey('--show', $properties['options']);
        $this->assertArrayHasKey('--force', $properties['options']);
    }
}
