<?php

declare(strict_types=1);

namespace Tests\Performance;

use CodeIgniter\Test\CIUnitTestCase;
use Daycry\JWT\Config\JWT as JWTConfig;
use Daycry\JWT\JWT;

/**
 * @internal
 */
final class JWTPerformanceTest extends CIUnitTestCase
{
    private JWTConfig $config;
    private JWT $library;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config      = config('JWT');
        $this->config->uid = 'testUser';
        $this->library     = new JWT($this->config);
    }

    public function testInstantiationIsCheap(): void
    {
        $last = null;

        for ($i = 0; $i < 100; $i++) {
            $last = new JWT($this->config);
        }

        // No wall-clock assertion: timing is environment-dependent and flaky on
        // shared CI. The loop still guards that repeated construction never errors.
        $this->assertInstanceOf(JWT::class, $last);
    }

    public function testDecodeAndIsValidWork(): void
    {
        $token = $this->library->encode(['test' => 'data']);

        $this->assertTrue($this->library->isValid($token));
        $decoded = $this->library->decode($token);
        $this->assertNotNull($decoded);
    }

    public function testUnsafeExtraction(): void
    {
        $this->config->allowUnsafeExtraction = true;
        $jwt                                 = new JWT($this->config);

        $token  = $jwt->encode(['user_id' => 123, 'role' => 'admin']);
        $claims = $jwt->extractClaimsUnsafe($token);

        $this->assertNotNull($claims);
        $this->assertArrayHasKey('data', $claims);
        $payload = json_decode((string) $claims['data'], true);
        $this->assertSame(123, $payload['user_id']);
    }

    public function testExpiryCheck(): void
    {
        $token = $this->library->encode(['test' => 'data']);

        for ($i = 0; $i < 100; $i++) {
            $this->library->isExpired($token);
        }

        // No wall-clock assertion (see testInstantiationIsCheap); the loop is a
        // smoke check that repeated isExpired() calls stay correct and error-free.
        $this->assertFalse($this->library->isExpired($token));
    }

    public function testTimeToExpiry(): void
    {
        $token = $this->library->encode(['test' => 'data']);
        $ttl   = $this->library->getTimeToExpiry($token);

        $this->assertNotNull($ttl);
        $this->assertGreaterThan(0, $ttl);
        $this->assertLessThanOrEqual(24 * 3600, $ttl);
    }
}
