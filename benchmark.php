<?php

/**
 * JWT Performance Benchmark Script
 * 
 * Run this script to measure JWT performance improvements
 * Usage: php benchmark.php
 */

require_once 'vendor/autoload.php';

use Daycry\JWT\JWT;
use Daycry\JWT\Config\JWT as JWTConfig;

// Simple configuration class for benchmark
class BenchmarkConfig extends JWTConfig
{
    public function __construct()
    {
        $this->uid = "benchmark_user";
        $this->signer = 'mBC5v1sOKVvbdEitdSBenu59nfNfhwkedkJVNabosTw=';
        $this->issuer = 'http://benchmark.local';
        $this->audience = 'http://benchmark.local';
        $this->identifier = 'bench123';
        $this->canOnlyBeUsedAfter = '+0 minute';
        $this->expiresAt = '+24 hour';
        $this->algorithm = \Lcobucci\JWT\Signer\Hmac\Sha256::class;
        $this->throwable = true;
        $this->validate = true;
        $this->validateClaims = [
            'SignedWith',
            'IssuedBy', 
            'ValidAt',
            'IdentifiedBy',
            'PermittedFor',
        ];
    }
}

function benchmarkFunction(callable $function, int $iterations = 1000): array
{
    $startTime = microtime(true);
    $startMemory = memory_get_usage();
    
    for ($i = 0; $i < $iterations; $i++) {
        $function();
    }
    
    $endTime = microtime(true);
    $endMemory = memory_get_usage();
    
    return [
        'time' => ($endTime - $startTime),
        'memory' => ($endMemory - $startMemory),
        'avg_time' => ($endTime - $startTime) / $iterations,
        'iterations' => $iterations
    ];
}

echo "🚀 JWT Performance Benchmark\n";
echo str_repeat("=", 50) . "\n";

$config = new BenchmarkConfig();

// Test 1: Object Creation (Lazy Loading)
echo "\n📦 Test 1: Object Creation (Lazy Loading)\n";
$result = benchmarkFunction(function() use ($config) {
    $jwt = new JWT($config);
}, 10000);

printf("⏱️  Total Time: %.4f seconds\n", $result['time']);
printf("📊 Avg per creation: %.6f seconds\n", $result['avg_time']);
printf("🧠 Memory used: %d bytes\n", $result['memory']);

// Test 2: Token Encoding
echo "\n🔐 Test 2: Token Encoding\n";
$jwt = new JWT($config);
$testData = ['user_id' => 123, 'role' => 'admin', 'permissions' => ['read', 'write']];

$result = benchmarkFunction(function() use ($jwt, $testData) {
    $jwt->encode($testData);
}, 5000);

printf("⏱️  Total Time: %.4f seconds\n", $result['time']);
printf("📊 Avg per encode: %.6f seconds\n", $result['avg_time']);
printf("🧠 Memory used: %d bytes\n", $result['memory']);

// Test 3: Token Decoding (with caching)
echo "\n🔓 Test 3: Token Decoding (with caching)\n";
$token = $jwt->encode($testData);

$result = benchmarkFunction(function() use ($jwt, $token) {
    $jwt->decode($token);
}, 5000);

printf("⏱️  Total Time: %.4f seconds\n", $result['time']);
printf("📊 Avg per decode: %.6f seconds\n", $result['avg_time']);
printf("🧠 Memory used: %d bytes\n", $result['memory']);

// Test 4: Fast Validation
echo "\n⚡ Test 4: Fast Validation (isValid)\n";
$result = benchmarkFunction(function() use ($jwt, $token) {
    $jwt->isValid($token);
}, 10000);

printf("⏱️  Total Time: %.4f seconds\n", $result['time']);
printf("📊 Avg per validation: %.6f seconds\n", $result['avg_time']);
printf("🧠 Memory used: %d bytes\n", $result['memory']);

// Test 5: Unsafe Extraction
echo "\n🔥 Test 5: Unsafe Claims Extraction\n";
$result = benchmarkFunction(function() use ($jwt, $token) {
    $jwt->extractClaimsUnsafe($token);
}, 10000);

printf("⏱️  Total Time: %.4f seconds\n", $result['time']);
printf("📊 Avg per extraction: %.6f seconds\n", $result['avg_time']);
printf("🧠 Memory used: %d bytes\n", $result['memory']);

// Test 6: Expiry Check
echo "\n⏳ Test 6: Expiry Check\n";
$result = benchmarkFunction(function() use ($jwt, $token) {
    $jwt->isExpired($token);
}, 15000);

printf("⏱️  Total Time: %.4f seconds\n", $result['time']);
printf("📊 Avg per check: %.6f seconds\n", $result['avg_time']);
printf("🧠 Memory used: %d bytes\n", $result['memory']);

// Memory Usage Summary
echo "\n" . str_repeat("=", 50) . "\n";
echo "📈 Memory Summary:\n";
printf("Peak memory usage: %d bytes (%.2f MB)\n", 
    memory_get_peak_usage(), 
    memory_get_peak_usage() / 1024 / 1024);
printf("Current memory usage: %d bytes (%.2f MB)\n", 
    memory_get_usage(), 
    memory_get_usage() / 1024 / 1024);

echo "\n✅ Benchmark completed!\n";
