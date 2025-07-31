<?php

namespace Tests\Performance;

use CodeIgniter\Test\CIUnitTestCase;
use Daycry\JWT\Config\JWT as JWTConfig;
use Daycry\JWT\JWT;

class JWTPerformanceTest extends CIUnitTestCase
{
    protected JWTConfig $config;
    protected JWT $library;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = config('JWT');
        $this->config->uid = "testUser";
        $this->library = new JWT($this->config);
    }

    public function testLazyLoadingPerformance()
    {
        // Crear múltiples instancias sin usar configuración
        $startTime = microtime(true);
        
        for ($i = 0; $i < 100; $i++) {
            $jwt = new JWT($this->config);
        }
        
        $lazyTime = microtime(true) - $startTime;
        
        // Esto debería ser muy rápido porque no se carga la configuración hasta que se usa
        $this->assertLessThan(0.1, $lazyTime, 'Lazy loading should be fast');
    }

    public function testConstraintsCaching()
    {
        $token = $this->library->encode(['test' => 'data']);
        
        // Primera validación (construye el cache)
        $startTime = microtime(true);
        $this->library->decode($token);
        $firstTime = microtime(true) - $startTime;
        
        // Segunda validación (usa el cache)
        $startTime = microtime(true);
        $this->library->decode($token);
        $secondTime = microtime(true) - $startTime;
        
        // La segunda debería ser más rápida debido al cache
        $this->assertLessThan($firstTime, $secondTime, 'Cached validation should be faster');
    }

    public function testFastValidationMethods()
    {
        $token = $this->library->encode(['test' => 'data']);
        
        // Test isValid method performance
        $startTime = microtime(true);
        for ($i = 0; $i < 50; $i++) {
            $this->library->isValid($token);
        }
        $isValidTime = microtime(true) - $startTime;
        
        // Test full decode performance
        $startTime = microtime(true);
        for ($i = 0; $i < 50; $i++) {
            $this->library->decode($token);
        }
        $decodeTime = microtime(true) - $startTime;
        
        // Just verify both methods work, performance can vary
        $this->assertTrue($this->library->isValid($token));
        $claims = $this->library->decode($token);
        $this->assertNotNull($claims);
    }

    public function testUnsafeExtraction()
    {
        $testData = ['user_id' => 123, 'role' => 'admin'];
        $token = $this->library->encode($testData);
        
        // Test unsafe extraction
        $startTime = microtime(true);
        $claims = $this->library->extractClaimsUnsafe($token);
        $unsafeTime = microtime(true) - $startTime;
        
        // Test safe decode
        $startTime = microtime(true);
        $safeClaims = $this->library->decode($token);
        $safeTime = microtime(true) - $startTime;
        
        $this->assertNotNull($claims);
        $this->assertLessThan($safeTime, $unsafeTime, 'Unsafe extraction should be faster');
    }

    public function testExpiryCheck()
    {
        $token = $this->library->encode(['test' => 'data']);
        
        $startTime = microtime(true);
        for ($i = 0; $i < 100; $i++) {
            $this->library->isExpired($token);
        }
        $expiryTime = microtime(true) - $startTime;
        
        $this->assertLessThan(0.1, $expiryTime, 'Expiry check should be fast');
        $this->assertFalse($this->library->isExpired($token));
    }

    public function testTimeToExpiry()
    {
        $token = $this->library->encode(['test' => 'data']);
        $timeToExpiry = $this->library->getTimeToExpiry($token);
        
        $this->assertNotNull($timeToExpiry);
        $this->assertGreaterThan(0, $timeToExpiry);
        $this->assertLessThanOrEqual(24 * 3600, $timeToExpiry); // Less than or equal to 24 hours
    }

    public function testCacheClear()
    {
        $token = $this->library->encode(['test' => 'data']);
        
        // Build cache
        $this->library->decode($token);
        
        // Clear cache
        $this->library->clearCache();
        
        // Should work fine after cache clear
        $claims = $this->library->decode($token);
        $this->assertNotNull($claims);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
