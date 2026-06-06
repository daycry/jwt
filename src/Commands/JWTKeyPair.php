<?php

declare(strict_types=1);

namespace Daycry\JWT\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Daycry\JWT\Commands\Concerns\PreparesPaths;
use OpenSSLAsymmetricKey;
use RuntimeException;
use Throwable;

class JWTKeyPair extends BaseCommand
{
    use PreparesPaths;

    /**
     * Supported ECDSA curves mapped to the lcobucci signer that matches each one.
     * A token signed on a given curve must be verified with the matching signer
     * (ES256/384/512), so the generated snippet suggests the right class.
     */
    private const ECDSA_CURVES = [
        'prime256v1' => '\\Lcobucci\\JWT\\Signer\\Ecdsa\\Sha256::class',
        'secp384r1'  => '\\Lcobucci\\JWT\\Signer\\Ecdsa\\Sha384::class',
        'secp521r1'  => '\\Lcobucci\\JWT\\Signer\\Ecdsa\\Sha512::class',
    ];

    protected $group       = 'JWT';
    protected $name        = 'jwt:keypair';
    protected $description = 'Generate an RSA or ECDSA key pair for asymmetric JWT signing.';
    protected $usage       = 'jwt:keypair [options]';

    /**
     * @var array<string, string>
     */
    protected $options = [
        '--algorithm'  => 'rsa | ecdsa (default: rsa)',
        '--bits'       => 'RSA key size in bits (default: 2048)',
        '--curve'      => 'ECDSA curve: prime256v1 | secp384r1 | secp521r1 (default: prime256v1)',
        '--output'     => 'Output directory (default: writable/keys)',
        '--name'       => 'Base file name without extension (default: jwt)',
        '--passphrase' => 'Encrypt the private key with this passphrase',
        '--force'      => 'Overwrite existing key files',
    ];

    public function run(array $params): int
    {
        try {
            $algorithm  = strtolower((string) (CLI::getOption('algorithm') ?? 'rsa'));
            $output     = (string) (CLI::getOption('output') ?? rtrim(WRITEPATH, '/\\') . DIRECTORY_SEPARATOR . 'keys');
            $name       = (string) (CLI::getOption('name') ?? 'jwt');
            $passphrase = CLI::getOption('passphrase');
            $force      = (bool) CLI::getOption('force');

            if (is_string($passphrase) && $passphrase !== '') {
                CLI::write(
                    '⚠️  Passing --passphrase on the command line can expose it via the process '
                    . 'list and shell history. Prefer a secrets manager or interactive entry.',
                    'yellow',
                );
            }

            $this->ensureDirectory($output, 0700);

            $privatePath = rtrim($output, '/\\') . DIRECTORY_SEPARATOR . $name . '-private.pem';
            $publicPath  = rtrim($output, '/\\') . DIRECTORY_SEPARATOR . $name . '-public.pem';

            if (! $force && (file_exists($privatePath) || file_exists($publicPath))) {
                CLI::error("Key files already exist at {$output}. Use --force to overwrite.");

                return EXIT_USER_INPUT;
            }

            $resource = $this->generateKeyResource($algorithm);

            $passphraseString = is_string($passphrase) && $passphrase !== '' ? $passphrase : null;
            $exported         = openssl_pkey_export($resource, $privatePem, $passphraseString);
            if (! $exported || ! is_string($privatePem)) {
                throw new RuntimeException('Failed to export private key: ' . openssl_error_string());
            }

            $details = openssl_pkey_get_details($resource);
            if ($details === false || ! isset($details['key'])) {
                throw new RuntimeException('Failed to read public key details: ' . openssl_error_string());
            }

            $publicPem = $details['key'];

            $this->writeKeyFile($privatePath, $privatePem, 0600);
            $this->writeKeyFile($publicPath, $publicPem, 0644);

            CLI::write('✅ Key pair generated:', 'green');
            CLI::write('  private: ' . $privatePath, 'cyan');
            CLI::write('  public : ' . $publicPath, 'cyan');
            CLI::newLine();
            CLI::write('Add these entries to your .env file:', 'yellow');
            CLI::write('jwt.algorithmType = "asymmetric"', 'cyan');
            CLI::write("jwt.signingKey    = \"{$privatePath}\"", 'cyan');
            CLI::write("jwt.verifyingKey  = \"{$publicPath}\"", 'cyan');
            if ($passphraseString !== null) {
                CLI::write('jwt.passphrase    = "<your-passphrase>"', 'cyan');
            }
            if ($algorithm === 'ecdsa') {
                $curve            = (string) (CLI::getOption('curve') ?? 'prime256v1');
                $signerSuggestion = self::ECDSA_CURVES[$curve] ?? self::ECDSA_CURVES['prime256v1'];
            } else {
                $signerSuggestion = '\\Lcobucci\\JWT\\Signer\\Rsa\\Sha256::class';
            }
            CLI::newLine();
            CLI::write('And in app/Config/JWT.php set:', 'yellow');
            CLI::write("public string \$algorithm = {$signerSuggestion};", 'cyan');
            CLI::newLine();
            CLI::write('⚠️  Keep the private key secret. Never commit it to version control.', 'red');

            return EXIT_SUCCESS;
        } catch (RuntimeException $e) {
            CLI::error($e->getMessage());

            return EXIT_ERROR;
        } catch (Throwable $e) {
            CLI::error('Unexpected error: ' . $e->getMessage());

            return EXIT_ERROR;
        }
    }

    /**
     * Persist a key file and lock down its permissions.
     *
     * On Windows `chmod()` is a silent no-op, so the operator is warned to restrict
     * access through NTFS ACLs instead of relying on POSIX mode bits.
     *
     * @throws RuntimeException When the file cannot be written.
     */
    protected function writeKeyFile(string $path, string $contents, int $mode): void
    {
        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException("Failed to write key file: {$path}");
        }

        if (PHP_OS_FAMILY === 'Windows') {
            CLI::write(
                '⚠️  ' . $path . ' cannot be restricted with chmod() on Windows. '
                . 'Lock it down with NTFS ACLs (e.g. icacls) so only the service account can read it.',
                'yellow',
            );

            return;
        }

        chmod($path, $mode);
    }

    /**
     * @return OpenSSLAsymmetricKey
     */
    private function generateKeyResource(string $algorithm)
    {
        if ($algorithm === 'rsa') {
            $bits = max(2048, (int) (CLI::getOption('bits') ?? 2048));

            $key = openssl_pkey_new([
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
                'private_key_bits' => $bits,
            ]);
        } elseif ($algorithm === 'ecdsa') {
            $curve = (string) (CLI::getOption('curve') ?? 'prime256v1');
            if (! isset(self::ECDSA_CURVES[$curve])) {
                throw new RuntimeException(sprintf(
                    'Unsupported ECDSA curve: %s. Use one of: %s.',
                    $curve,
                    implode(', ', array_keys(self::ECDSA_CURVES)),
                ));
            }

            $key = openssl_pkey_new([
                'private_key_type' => OPENSSL_KEYTYPE_EC,
                'curve_name'       => $curve,
            ]);
        } else {
            throw new RuntimeException("Unsupported algorithm: {$algorithm}. Use 'rsa' or 'ecdsa'.");
        }

        if ($key === false) {
            throw new RuntimeException('openssl_pkey_new() failed: ' . openssl_error_string());
        }

        return $key;
    }
}
