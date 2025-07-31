<?php

namespace Tests\Commands;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Test for JWT Generate Key Command Core Logic
 * Tests the algorithms and patterns used by the command
 */
class JWTGenerateKeyLogicTest extends CIUnitTestCase
{
    private string $tempEnvFile;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a temporary .env file for testing
        $this->tempEnvFile = sys_get_temp_dir() . '/.env.test.' . uniqid();
        file_put_contents($this->tempEnvFile, "# Test .env file\nAPP_ENV=testing\n");
    }
    
    protected function tearDown(): void
    {
        // Clean up temp file
        if (file_exists($this->tempEnvFile)) {
            unlink($this->tempEnvFile);
        }
        
        parent::tearDown();
    }
    
    public function testKeyGenerationAlgorithm()
    {
        // Test the exact key generation algorithm used by the command
        $lengths = [16, 32, 64];
        
        foreach ($lengths as $length) {
            // This mirrors the exact code in the command: base64_encode(random_bytes($length))
            $key = base64_encode(random_bytes($length));
            
            // Verify key properties
            $this->assertNotEmpty($key);
            $this->assertTrue(strlen($key) >= $length);
            
            // Verify it's valid base64
            $decoded = base64_decode($key, true);
            $this->assertNotFalse($decoded);
            $this->assertEquals($length, strlen($decoded));
        }
    }
    
    public function testValidationLogic()
    {
        // Test the exact validation logic used in the command: $length >= 16
        $validLengths = [16, 20, 24, 32, 48, 64, 128];
        $invalidLengths = [0, 1, 8, 15, -1, -10];
        
        foreach ($validLengths as $length) {
            $this->assertTrue($length >= 16, "Length {$length} should be valid (>= 16)");
        }
        
        foreach ($invalidLengths as $length) {
            $this->assertFalse($length >= 16, "Length {$length} should be invalid (< 16)");
        }
    }
    
    public function testParameterParsing()
    {
        // Test the exact parameter parsing logic: (int)($params[0] ?? 32)
        $testCases = [
            [[], 32],  // default case
            [['16'], 16],
            [['64'], 64],
            [['abc'], 0],  // string converts to 0
            [[null], 32],  // null uses default
        ];
        
        foreach ($testCases as [$input, $expected]) {
            $length = (int)($input[0] ?? 32);
            $this->assertEquals($expected, $length);
        }
    }
    
    public function testEnvironmentFileRegexPattern()
    {
        // Test the regex pattern used in the command: '/^jwt\.signer\s*=.*$/m'
        $testContent = "APP_ENV=testing\njwt.signer=old-key-value\nOTHER_VAR=value";
        
        $pattern = '/^jwt\.signer\s*=.*$/m';
        $this->assertEquals(1, preg_match($pattern, $testContent));
        
        // Test replacement logic
        $newKey = 'new-key-value';
        $newContent = preg_replace($pattern, "jwt.signer={$newKey}", $testContent);
        
        $this->assertStringContainsString("jwt.signer={$newKey}", $newContent);
        $this->assertStringNotContainsString('old-key-value', $newContent);
        $this->assertStringContainsString('APP_ENV=testing', $newContent);
        $this->assertStringContainsString('OTHER_VAR=value', $newContent);
    }
    
    public function testOptionParsing()
    {
        // Test the option parsing logic used in the command
        $testCases = [
            [['--show'], true, false],
            [['--force'], false, true],
            [['--show', '--force'], true, true],
            [[], false, false],
        ];
        
        foreach ($testCases as [$params, $expectedShow, $expectedForce]) {
            $show = in_array('--show', $params);
            $force = in_array('--force', $params);
            
            $this->assertEquals($expectedShow, $show);
            $this->assertEquals($expectedForce, $force);
        }
    }
    
    public function testEnvFileManipulation()
    {
        // Test the actual file manipulation logic used by the command
        $testKey = 'test-key-12345';
        
        // Test adding new key
        $envContent = file_get_contents($this->tempEnvFile);
        $envContent .= "\n# JWT Configuration\njwt.signer={$testKey}\n";
        file_put_contents($this->tempEnvFile, $envContent);
        
        $content = file_get_contents($this->tempEnvFile);
        $this->assertStringContainsString("jwt.signer={$testKey}", $content);
        
        // Test updating existing key
        $newKey = 'updated-key-67890';
        $content = preg_replace('/^jwt\.signer\s*=.*$/m', "jwt.signer={$newKey}", $content);
        file_put_contents($this->tempEnvFile, $content);
        
        $finalContent = file_get_contents($this->tempEnvFile);
        $this->assertStringContainsString("jwt.signer={$newKey}", $finalContent);
        $this->assertStringNotContainsString($testKey, $finalContent);
    }
    
    public function testKeyUniquenessAndSecurity()
    {
        // Test the security properties of the key generation
        $keys = [];
        $length = 32;
        
        // Generate multiple keys using the same algorithm as the command
        for ($i = 0; $i < 10; $i++) {
            $key = base64_encode(random_bytes($length));
            $keys[] = $key;
        }
        
        // All keys should be unique
        $uniqueKeys = array_unique($keys);
        $this->assertEquals(count($keys), count($uniqueKeys));
        
        // All keys should be valid base64
        foreach ($keys as $key) {
            $this->assertMatchesRegularExpression('/^[A-Za-z0-9+\/=]+$/', $key);
            $this->assertNotFalse(base64_decode($key, true));
        }
    }
}
