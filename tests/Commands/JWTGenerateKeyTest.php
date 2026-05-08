<?php

declare(strict_types=1);

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

    /**
     * @var list<mixed>
     */
    private array $originalOptions = [];

    private ?string $tmpDir        = null;
    private string $envPath        = '';
    private string $envExamplePath = '';

    protected function setUp(): void
    {
        parent::setUp();

        $reflection            = new ReflectionProperty(CLI::class, 'options');
        $this->originalOptions = $reflection->getValue();

        $this->tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'jwt-key-test-' . uniqid('', true);
        mkdir($this->tmpDir, 0700, true);
        $this->envPath        = $this->tmpDir . DIRECTORY_SEPARATOR . '.env';
        $this->envExamplePath = $this->tmpDir . DIRECTORY_SEPARATOR . '.env.example';
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
     * @param list<mixed> $options
     */
    private function setCliOptions(array $options): void
    {
        $reflection = new ReflectionProperty(CLI::class, 'options');
        $reflection->setValue(null, $options);
    }

    /**
     * @param list<string> $params
     * @param list<mixed>  $options
     */
    private function runCommand(array $params, array $options = []): string
    {
        $this->setCliOptions($options);
        $this->resetStreamFilterBuffer();

        $command = new JWTGenerateKey(new Logger(new LoggerConfig()), new Commands());
        $command->run($params);

        return $this->getStreamFilterBuffer();
    }

    /**
     * @param list<string> $params
     * @param list<mixed>  $options
     */
    private function runCommandSandboxed(array $params, array $options = []): string
    {
        $this->setCliOptions($options);
        $this->resetStreamFilterBuffer();

        $command = new class (new Logger(new LoggerConfig()), new Commands(), $this->envPath, $this->envExamplePath) extends JWTGenerateKey {
            public function __construct(
                $logger,
                $commands,
                private readonly string $sandboxEnv,
                private readonly string $sandboxEnvExample,
            ) {
                parent::__construct($logger, $commands);
            }

            protected function envPath(): string
            {
                return $this->sandboxEnv;
            }

            protected function envExamplePath(): string
            {
                return $this->sandboxEnvExample;
            }
        };

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

    public function testEnvFileMissingShowsErrorAndExampleHint(): void
    {
        // No .env exists in the sandbox.
        file_put_contents($this->envExamplePath, "APP_ENV=testing\n");

        $output = $this->runCommandSandboxed(['32']);

        $this->assertStringContainsString('.env file not found', $output);
        $this->assertStringContainsString('cp ', $output);
    }

    public function testEnvFileMissingWithoutExample(): void
    {
        $output = $this->runCommandSandboxed(['32']);

        $this->assertStringContainsString('.env file not found', $output);
    }

    public function testAppendsKeyToEnvWhenMissing(): void
    {
        file_put_contents($this->envPath, "APP_ENV=testing\nOTHER=1\n");

        $output = $this->runCommandSandboxed(['32']);

        $this->assertStringContainsString('successfully added', $output);
        $env = (string) file_get_contents($this->envPath);
        $this->assertMatchesRegularExpression('/^jwt\.signer=[A-Za-z0-9+\/=]+$/m', $env);
        $this->assertStringContainsString('# JWT Configuration', $env);
        $this->assertStringContainsString('APP_ENV=testing', $env, 'Existing entries must be preserved.');
    }

    public function testForceOverwritesExistingSigner(): void
    {
        file_put_contents($this->envPath, "jwt.signer=old-key\n");

        $output = $this->runCommandSandboxed(['32'], ['force' => null]);

        $this->assertStringContainsString('successfully added', $output);
        $env = (string) file_get_contents($this->envPath);
        $this->assertStringNotContainsString('old-key', $env);
    }

    public function testTestingEnvironmentSkipsPromptOnExistingSigner(): void
    {
        // CI_ENVIRONMENT=testing is set by phpunit.xml.dist; the command must
        // therefore overwrite without --force during the suite.
        file_put_contents($this->envPath, "jwt.signer=old-key\n");

        $output = $this->runCommandSandboxed(['32']);

        $this->assertStringContainsString('successfully added', $output);
        $env = (string) file_get_contents($this->envPath);
        $this->assertStringNotContainsString('old-key', $env);
    }

    public function testCommandMetadata(): void
    {
        $reflection = new ReflectionClass(JWTGenerateKey::class);
        $properties = $reflection->getDefaultProperties();

        $this->assertSame('JWT', $properties['group']);
        $this->assertSame('jwt:key', $properties['name']);
        $this->assertStringContainsString('Generate a secure JWT signing key', (string) $properties['description']);
        $this->assertArrayHasKey('--show', $properties['options']);
        $this->assertArrayHasKey('--force', $properties['options']);
    }
}
