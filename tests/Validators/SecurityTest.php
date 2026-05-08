<?php

declare(strict_types=1);

namespace Tests\Validators;

use CodeIgniter\Test\CIUnitTestCase;
use Daycry\JWT\Config\JWT as JWTConfig;
use Daycry\JWT\Exceptions\InvalidTokenException;
use Daycry\JWT\Exceptions\JWTConfigurationException;
use Daycry\JWT\JWT;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Validation\RequiredConstraintsViolated;
use stdClass;

/**
 * @internal
 */
final class SecurityTest extends CIUnitTestCase
{
    private JWTConfig $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config      = config('JWT');
        $this->config->uid = 'security-test';
    }

    public function testRefusesToEncodeWhenSignerIsMissing(): void
    {
        $this->config->signer = null;
        $jwt                  = new JWT($this->config);

        $this->expectException(JWTConfigurationException::class);
        $jwt->encode('payload');
    }

    public function testRefusesToEncodeWhenSignerIsEmpty(): void
    {
        $this->config->signer = '';
        $jwt                  = new JWT($this->config);

        $this->expectException(JWTConfigurationException::class);
        $jwt->encode('payload');
    }

    public function testTamperedSignatureFailsValidation(): void
    {
        $jwt   = new JWT($this->config);
        $token = $jwt->encode('payload');

        $this->expectException(RequiredConstraintsViolated::class);
        $jwt->decode($this->mutateSignature($token));
    }

    public function testMalformedTokenThrowsInvalidTokenException(): void
    {
        $jwt = new JWT($this->config);

        $this->expectException(InvalidTokenException::class);
        $jwt->decode('not-a-real-jwt');
    }

    public function testWrongIdentifierFailsValidation(): void
    {
        $jwt   = new JWT($this->config);
        $token = $jwt->encode('payload');

        $this->config->identifier = 'a-different-identifier';
        $verifier                 = new JWT($this->config);

        $this->expectException(RequiredConstraintsViolated::class);
        $verifier->decode($token);
    }

    public function testWrongAudienceFailsValidation(): void
    {
        $jwt   = new JWT($this->config);
        $token = $jwt->encode('payload');

        $this->config->audience = 'https://other-app.example.com';
        $verifier               = new JWT($this->config);

        $this->expectException(RequiredConstraintsViolated::class);
        $verifier->decode($token);
    }

    public function testWrongIssuerFailsValidation(): void
    {
        $jwt   = new JWT($this->config);
        $token = $jwt->encode('payload');

        $this->config->issuer = 'https://other-issuer.example.com';
        $verifier             = new JWT($this->config);

        $this->expectException(RequiredConstraintsViolated::class);
        $verifier->decode($token);
    }

    public function testIsValidReturnsFalseForTamperedToken(): void
    {
        $jwt   = new JWT($this->config);
        $token = $jwt->encode('payload');

        $this->assertFalse($jwt->isValid($this->mutateSignature($token)));
    }

    public function testIsValidReturnsFalseForMalformedToken(): void
    {
        $jwt = new JWT($this->config);

        $this->assertFalse($jwt->isValid('garbage'));
    }

    public function testTryDecodeReturnsNullForTamperedToken(): void
    {
        $jwt   = new JWT($this->config);
        $token = $jwt->encode('payload');

        $this->assertNotInstanceOf(Plain::class, $jwt->tryDecode($this->mutateSignature($token)));
    }

    public function testExtractClaimsUnsafeReturnsNullForMalformedToken(): void
    {
        $this->config->allowUnsafeExtraction = true;
        $jwt                                 = new JWT($this->config);

        $this->assertNull($jwt->extractClaimsUnsafe('garbage'));
    }

    public function testIsExpiredReturnsTrueForMalformedToken(): void
    {
        $jwt = new JWT($this->config);

        $this->assertTrue($jwt->isExpired('garbage'));
    }

    public function testGetTimeToExpiryReturnsNullForMalformedToken(): void
    {
        $jwt = new JWT($this->config);

        $this->assertNull($jwt->getTimeToExpiry('garbage'));
    }

    public function testEncodeWithObjectPayload(): void
    {
        $jwt = new JWT($this->config);

        $payload         = new stdClass();
        $payload->userId = 42;
        $payload->role   = 'admin';

        $token   = $jwt->encode($payload);
        $decoded = $jwt->decode($token);

        $this->assertInstanceOf(Plain::class, $decoded);
        $payloadArray = json_decode((string) $decoded->claims()->get('data'), true);
        $this->assertSame(42, $payloadArray['userId']);
        $this->assertSame('admin', $payloadArray['role']);
    }

    public function testCustomParamDataIsRespected(): void
    {
        $jwt = (new JWT($this->config))->withParamData('payload');

        $this->assertSame('payload', $jwt->getParamData());

        $token   = $jwt->encode('hello');
        $decoded = $jwt->decode($token);

        $this->assertInstanceOf(Plain::class, $decoded);
        $this->assertSame('hello', $decoded->claims()->get('payload'));
    }

    public function testGetPayloadAutoDecodesCompactArray(): void
    {
        $jwt     = new JWT($this->config);
        $payload = ['user_id' => 42, 'role' => 'admin'];

        $token = $jwt->encode($payload);

        $this->assertSame($payload, $jwt->getPayload($token));
    }

    public function testGetPayloadReturnsScalarForScalarPayload(): void
    {
        $jwt   = new JWT($this->config);
        $token = $jwt->encode('hello');

        $this->assertSame('hello', $jwt->getPayload($token));
    }

    public function testGetPayloadThrowsOnTamperedToken(): void
    {
        $jwt   = new JWT($this->config);
        $token = $jwt->encode(['x' => 1]);

        $this->expectException(RequiredConstraintsViolated::class);
        $jwt->getPayload($this->mutateSignature($token));
    }

    public function testZeroUidIsPreservedInToken(): void
    {
        $jwt   = new JWT($this->config);
        $token = $jwt->encode('payload', 0);

        $decoded = $jwt->decode($token);
        $this->assertInstanceOf(Plain::class, $decoded);
        $this->assertSame(0, $decoded->claims()->get('uid'));
    }

    public function testInvalidAlgorithmTypeIsRejected(): void
    {
        $this->config->algorithmType = 'magic';
        $jwt                         = new JWT($this->config);

        $this->expectException(JWTConfigurationException::class);
        $this->expectExceptionMessage('algorithmType must be');
        $jwt->encode('payload');
    }

    public function testUnknownValidationConstraintIsRejected(): void
    {
        $jwt   = new JWT($this->config);
        $token = $jwt->encode('payload');

        $this->config->validateClaims = ['SignedWith', 'NotARealConstraint'];
        $verifier                     = new JWT($this->config);

        $this->expectException(JWTConfigurationException::class);
        $this->expectExceptionMessage('Unknown validation constraint');
        $verifier->decode($token);
    }

    public function testMissingIssuerIsRejected(): void
    {
        $this->config->issuer = null;
        $jwt                  = new JWT($this->config);

        $this->expectException(JWTConfigurationException::class);
        $this->expectExceptionMessage('"issuer"');
        $jwt->encode('payload');
    }

    public function testStrictValidAtRequiresAllTimeClaims(): void
    {
        $this->config->validateClaims = ['SignedWith', 'StrictValidAt'];
        $jwt                          = new JWT($this->config);

        $token   = $jwt->encode('payload');
        $decoded = $jwt->decode($token);

        $this->assertSame('payload', $decoded->claims()->get('data'));
    }

    public function testJwtForFactoryFallsBackToFrameworkConfig(): void
    {
        $jwt = JWT::for();

        $token   = $jwt->encode('payload');
        $decoded = $jwt->decode($token);

        $this->assertSame('payload', $decoded->claims()->get('data'));
    }

    public function testWithLeewayProducesNewInstance(): void
    {
        $jwt    = new JWT($this->config);
        $leeway = $jwt->withLeeway(60);

        $this->assertNotSame($jwt, $leeway);

        $token = $leeway->encode('payload');
        $this->assertSame('payload', $leeway->decode($token)->claims()->get('data'));
    }

    public function testTryDecodeReturnsPlainOnSuccess(): void
    {
        $jwt   = new JWT($this->config);
        $token = $jwt->encode('payload');

        $decoded = $jwt->tryDecode($token);

        $this->assertInstanceOf(Plain::class, $decoded);
        $this->assertSame('payload', $decoded->claims()->get('data'));
    }

    public function testGetPayloadWithSplitModeReturnsClaimValue(): void
    {
        $jwt = (new JWT($this->config))->withSplitData();

        $token = $jwt->encode(['hello' => 'world']);

        // Split mode does not set cty=json, so getPayload returns the raw claim
        // value at $paramData (defaults to 'data') — null in this case.
        $this->assertNull($jwt->getPayload($token));
    }

    public function testIsExpiredFalseForFreshToken(): void
    {
        $jwt   = new JWT($this->config);
        $token = $jwt->encode('payload');

        $this->assertFalse($jwt->isExpired($token));
    }

    public function testGetTimeToExpiryForFreshTokenReturnsPositiveInteger(): void
    {
        $jwt   = new JWT($this->config);
        $token = $jwt->encode('payload');

        $ttl = $jwt->getTimeToExpiry($token);

        $this->assertNotNull($ttl);
        $this->assertGreaterThan(0, $ttl);
    }

    public function testExtractClaimsUnsafeReturnsAllClaimsWhenAllowed(): void
    {
        $this->config->allowUnsafeExtraction = true;
        $jwt                                 = new JWT($this->config);

        $token  = $jwt->encode(['x' => 1], 'user-99');
        $claims = $jwt->extractClaimsUnsafe($token);

        $this->assertNotNull($claims);
        $this->assertSame('user-99', $claims['uid']);
        $this->assertArrayHasKey('iss', $claims);
        $this->assertArrayHasKey('aud', $claims);
        $this->assertArrayHasKey('exp', $claims);
    }

    public function testExtractClaimsUnsafeWithoutAllowFlagStillWorksButLogs(): void
    {
        // The default allowUnsafeExtraction=false attempts a log_message warning.
        $jwt = new JWT($this->config);

        $token  = $jwt->encode('payload');
        $claims = $jwt->extractClaimsUnsafe($token);

        $this->assertNotNull($claims);
    }

    public function testAsymmetricConfigRejectsMissingSigningKey(): void
    {
        $this->config->algorithmType = 'asymmetric';
        $this->config->signingKey    = null;
        $jwt                         = new JWT($this->config);

        $this->expectException(JWTConfigurationException::class);
        $this->expectExceptionMessage('"signingKey"');
        $jwt->encode('payload');
    }

    public function testAsymmetricConfigRejectsMissingVerifyingKey(): void
    {
        $this->config->algorithmType = 'asymmetric';
        $this->config->signingKey    = 'dummy';
        $this->config->verifyingKey  = null;
        $jwt                         = new JWT($this->config);

        $this->expectException(JWTConfigurationException::class);
        $this->expectExceptionMessage('"verifyingKey"');
        $jwt->encode('payload');
    }

    private function mutateSignature(string $token): string
    {
        $segments    = explode('.', $token);
        $first       = $segments[2][0];
        $segments[2] = ($first === 'X' ? 'Y' : 'X') . substr($segments[2], 1);

        return implode('.', $segments);
    }
}
