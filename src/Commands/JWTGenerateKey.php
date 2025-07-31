<?php

namespace Daycry\JWT\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class JWTGenerateKey extends BaseCommand
{
    protected $group = 'JWT';
    protected $name = 'jwt:key';
    protected $description = 'Generate a secure JWT signing key';
    protected $usage = 'jwt:key [length]';
    protected $arguments = [
        'length' => 'Key length in bytes (default: 32)'
    ];
    protected $options = [
        '--show' => 'Display the key instead of updating .env file',
        '--force' => 'Force overwrite existing key in .env'
    ];

    public function run(array $params)
    {
        $length = (int)($params[0] ?? 32);
        
        // Validate length
        if ($length < 16) {
            CLI::error('Key length must be at least 16 bytes for security');
            return;
        }
        
        if ($length > 128) {
            CLI::error('Key length cannot exceed 128 bytes');
            return;
        }

        try {
            // Generate secure random key
            $key = base64_encode(random_bytes($length));
            
            if (CLI::getOption('show')) {
                $this->displayKey($key, $length);
                return;
            }
            
            $this->updateEnvFile($key);
            
        } catch (\Exception $e) {
            CLI::error('Failed to generate key: ' . $e->getMessage());
        }
    }

    private function displayKey(string $key, int $length): void
    {
        CLI::write('Generated JWT Key (' . $length . ' bytes):', 'yellow');
        CLI::write($key, 'green');
        CLI::newLine();
        CLI::write('Add this to your .env file:', 'yellow');
        CLI::write("jwt.signer={$key}", 'cyan');
        CLI::newLine();
        CLI::write('⚠️  Keep this key secure and never commit it to version control!', 'red');
    }

    private function updateEnvFile(string $key): void
    {
        $envPath = ROOTPATH . '.env';
        $envExamplePath = ROOTPATH . '.env.example';
        
        // Check if .env exists
        if (!file_exists($envPath)) {
            CLI::error('.env file not found. Please create one first.');
            CLI::write('You can copy from .env.example if it exists:', 'yellow');
            if (file_exists($envExamplePath)) {
                CLI::write("cp {$envExamplePath} {$envPath}", 'cyan');
            }
            return;
        }

        $envContent = file_get_contents($envPath);
        
        // Check if jwt.signer already exists
        if (preg_match('/^jwt\.signer\s*=.*$/m', $envContent)) {
            // In testing environment or with --force flag, skip prompt
            $isTestingEnv = (getenv('CI_ENVIRONMENT') === 'testing' || getenv('APP_ENV') === 'testing');
            $forceOverwrite = CLI::getOption('force') || $isTestingEnv;
            
            if (!$forceOverwrite && CLI::prompt('JWT key already exists in .env. Overwrite?', ['y', 'n']) === 'n') {
                CLI::write('Operation cancelled.', 'yellow');
                return;
            }
            
            // Update existing key
            $envContent = preg_replace('/^jwt\.signer\s*=.*$/m', "jwt.signer={$key}", $envContent);
        } else {
            // Add new key
            $envContent .= "\n# JWT Configuration\njwt.signer={$key}\n";
        }

        if (file_put_contents($envPath, $envContent)) {
            CLI::write('✅ JWT key successfully added to .env file', 'green');
            CLI::write('Generated key: ' . $key, 'cyan');
            CLI::newLine();
            CLI::write('⚠️  Keep your .env file secure and never commit it to version control!', 'red');
        } else {
            CLI::error('Failed to write to .env file. Check permissions.');
        }
    }
}
