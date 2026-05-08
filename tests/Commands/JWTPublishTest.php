<?php

declare(strict_types=1);

namespace Tests\Commands;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\StreamFilterTrait;
use Config\Autoload;
use Daycry\JWT\Commands\JWTPublish;
use ReflectionClass;

/**
 * @internal
 */
final class JWTPublishTest extends CIUnitTestCase
{
    use StreamFilterTrait;

    private string $publishedConfigPath;

    protected function setUp(): void
    {
        parent::setUp();

        $autoload = new Autoload();
        $appPath  = $autoload->psr4[APP_NAMESPACE];
        if (is_array($appPath)) {
            $appPath = $appPath[0];
        }
        $this->publishedConfigPath = $appPath . 'Config/JWT.php';

        if (file_exists($this->publishedConfigPath)) {
            unlink($this->publishedConfigPath);
        }
    }

    protected function tearDown(): void
    {
        if (file_exists($this->publishedConfigPath)) {
            unlink($this->publishedConfigPath);
        }
        parent::tearDown();
    }

    public function testPublishCreatesConfigFile(): void
    {
        command('jwt:publish');

        $this->assertFileExists($this->publishedConfigPath);
    }

    public function testPublishedFileHasCorrectNamespaceAndExtension(): void
    {
        command('jwt:publish');
        $content = file_get_contents($this->publishedConfigPath);

        $this->assertStringContainsString('namespace Config;', (string) $content);
        $this->assertStringContainsString('extends \Daycry\JWT\Config\JWT', (string) $content);
        $this->assertStringNotContainsString('namespace Daycry\JWT\Config;', (string) $content);
        $this->assertStringNotContainsString('extends BaseConfig', (string) $content);
    }

    public function testCommandMetadata(): void
    {
        $reflection = new ReflectionClass(JWTPublish::class);
        $properties = $reflection->getDefaultProperties();

        $this->assertSame('JWT', $properties['group']);
        $this->assertSame('jwt:publish', $properties['name']);
        $this->assertStringContainsString('JWT config file publisher', (string) $properties['description']);
    }
}
