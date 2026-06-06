<?php

declare(strict_types=1);

namespace Daycry\JWT\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Autoload;
use Daycry\JWT\Commands\Concerns\PreparesPaths;
use RuntimeException;
use Throwable;

class JWTPublish extends BaseCommand
{
    use PreparesPaths;

    protected $group             = 'JWT';
    protected $name              = 'jwt:publish';
    protected $description       = 'JWT config file publisher.';
    protected string $sourcePath = '';

    public function run(array $params): int
    {
        try {
            $this->determineSourcePath();
            if ($this->publishConfig() === false) {
                return EXIT_USER_INPUT;
            }
        } catch (RuntimeException $e) {
            CLI::error($e->getMessage());

            return EXIT_ERROR;
        } catch (Throwable $e) {
            CLI::error('Unexpected error: ' . $e->getMessage());

            return EXIT_ERROR;
        }

        CLI::write('Config file was successfully generated.', 'green');

        return EXIT_SUCCESS;
    }

    /**
     * Determine the package source directory.
     *
     * @throws RuntimeException When the source path cannot be resolved.
     */
    protected function determineSourcePath(): void
    {
        $resolved = realpath(__DIR__ . '/../');

        if ($resolved === false || $resolved === '/') {
            throw new RuntimeException('Unable to determine the correct source directory.');
        }

        $this->sourcePath = $resolved;
    }

    /**
     * Copy and rewrite the bundled config file into the application.
     *
     * @return bool True on write, false when the user declined to overwrite.
     */
    protected function publishConfig(): bool
    {
        $path     = $this->sourcePath . '/Config/JWT.php';
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Unable to read source config at {$path}");
        }

        $contents = str_replace('namespace Daycry\JWT\Config;', 'namespace Config;', $contents);
        $contents = str_replace('extends BaseConfig', 'extends \Daycry\JWT\Config\JWT', $contents);

        return $this->writeFile('Config/JWT.php', $contents);
    }

    /**
     * Write the published file into the application namespace.
     *
     * @return bool True on write, false when the user declined.
     *
     * @throws RuntimeException When the destination cannot be written.
     */
    protected function writeFile(string $path, string $content): bool
    {
        $autoload = new Autoload();
        $appPath  = $autoload->psr4[APP_NAMESPACE];
        if (is_array($appPath)) {
            $appPath = $appPath[0];
        }
        $directory = dirname($appPath . $path);

        $this->ensureDirectory($directory, 0777);

        if (
            file_exists($appPath . $path)
            && CLI::prompt('Config file already exists, do you want to replace it?', ['y', 'n']) === 'n'
        ) {
            CLI::error('Cancelled');

            return false;
        }

        $written = file_put_contents($appPath . $path, $content);
        if ($written === false) {
            throw new RuntimeException("Failed to write file: {$appPath}{$path}");
        }

        CLI::write(CLI::color('Created: ', 'yellow') . $path);

        return true;
    }
}
