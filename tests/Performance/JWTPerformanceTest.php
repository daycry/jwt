<?php

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
        $start = microtime(true);
        for ($i = 0; $i < 100; $i++) {
            new JWT($this->config);
        }
        $elapsed = microtime(true) - $start;

        $this->assertLessThan(0.5, $elapsed, 'Instantiating 100 JWT objects should be under 500 ms');
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
        $this->assertSame(123, $claims['data'] ?? null ? json_decode($claims['data'], true)['user_id'] ?? null : null);
    }

    public function testExpiryCheck(): void
    {
        $token = $this->library->encode(['test' => 'data']);

        $start = microtime(true);
        for ($i = 0; $i < 100; $i++) {
            $this->library->isExpired($token);
        }
        $elapsed = microtime(true) - $start;

        $this->assertLessThan(0.5, $elapsed, '100 isExpired() calls should be under 500 ms');
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
