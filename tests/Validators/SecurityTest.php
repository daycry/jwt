<?php

declare(strict_types=1);

namespace Tests\Validators;

use CodeIgniter\Test\CIUnitTestCase;
use DateTimeImmutable;
use Daycry\JWT\Config\JWT as JWTConfig;
use Daycry\JWT\Exceptions\InvalidTokenException;
use Daycry\JWT\Exceptions\JWTConfigurationException;
use Daycry\JWT\JWT;
use InvalidArgumentException;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
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

    public function testIntegerUidFromConfigIsPreserved(): void
    {
        $this->config->uid = 99;
        $jwt               = new JWT($this->config);

        $token   = $jwt->encode('payload');
        $decoded = $jwt->decode($token);

        $this->assertSame(99, $decoded->claims()->get('uid'));
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

    public function testEmptyIssuerIsRejected(): void
    {
        $this->config->issuer = '';
        $jwt                  = new JWT($this->config);

        $this->expectException(JWTConfigurationException::class);
        $this->expectExceptionMessage('"issuer"');
        $jwt->encode('payload');
    }

    public function testEmptyAudienceIsRejected(): void
    {
        $this->config->audience = '';
        $jwt                    = new JWT($this->config);

        $this->expectException(JWTConfigurationException::class);
        $this->expectExceptionMessage('"audience"');
        $jwt->encode('payload');
    }

    public function testEmptyIdentifierIsRejected(): void
    {
        $this->config->identifier = '';
        $jwt                      = new JWT($this->config);

        $this->expectException(JWTConfigurationException::class);
        $this->expectExceptionMessage('"identifier"');
        $jwt->encode('payload');
    }

    public function testDecodeRejectsValidateClaimsWithoutSignedWith(): void
    {
        $jwt   = new JWT($this->config);
        $token = $jwt->encode('payload');

        $this->config->validateClaims = ['IssuedBy', 'LooseValidAt'];
        $verifier                     = new JWT($this->config);

        $this->expectException(JWTConfigurationException::class);
        $this->expectExceptionMessage('SignedWith');
        $verifier->decode($token);
    }

    public function testValidateFalseLogsWarningOnDecode(): void
    {
        $jwt   = new JWT($this->config);
        $token = $jwt->encode('payload');

        $this->config->validate = false;
        $verifier               = new JWT($this->config);
        $decoded                = $verifier->decode($token);

        $this->assertInstanceOf(Plain::class, $decoded);
        $this->assertLogContains('warning', 'validate = false');
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
        $this->assertLogContains('warning', 'extractClaimsUnsafe()');
    }

    public function testAsymmetricTypeWithHmacAlgorithmIsRejected(): void
    {
        $this->config->algorithmType = 'asymmetric';
        $this->config->signingKey    = 'dummy-private';
        $this->config->verifyingKey  = 'dummy-public';
        // $algorithm stays the default Hmac\Sha256 — incompatible with asymmetric.
        $jwt = new JWT($this->config);

        $this->expectException(JWTConfigurationException::class);
        $this->expectExceptionMessage('not compatible');
        $jwt->encode('payload');
    }

    public function testSymmetricTypeWithAsymmetricAlgorithmIsRejected(): void
    {
        $this->config->algorithm = Sha256::class;
        // algorithmType stays 'symmetric' — incompatible with an RSA signer.
        $jwt = new JWT($this->config);

        $this->expectException(JWTConfigurationException::class);
        $this->expectExceptionMessage('not compatible');
        $jwt->encode('payload');
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

    public function testEncodeThrowsOnInvalidExpiresAtModifier(): void
    {
        $this->config->expiresAt = 'not a real modifier';
        $jwt                     = new JWT($this->config);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('expiresAt');
        $jwt->encode('payload');
    }

    public function testEncodeThrowsOnInvalidCanOnlyBeUsedAfterModifier(): void
    {
        $this->config->canOnlyBeUsedAfter = 'not a real modifier';
        $jwt                              = new JWT($this->config);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('canOnlyBeUsedAfter');
        $jwt->encode('payload');
    }

    public function testFutureCanOnlyBeUsedAfterIsClampedToIssuance(): void
    {
        $this->config->canOnlyBeUsedAfter = '+1 day';
        $jwt                              = new JWT($this->config);
        $token                            = $jwt->encode('payload');

        // Clamped to iat, so the token is immediately valid rather than "not yet valid".
        $decoded = $jwt->decode($token);
        $this->assertSame('payload', $decoded->claims()->get('data'));
        $this->assertSame(
            $decoded->claims()->get('iat')->getTimestamp(),
            $decoded->claims()->get('nbf')->getTimestamp(),
        );
    }

    public function testIsExpiredAndTtlAreLenientForTokenWithoutExpClaim(): void
    {
        $token = $this->encodeTokenWithoutExpiry('payload');
        $jwt   = new JWT($this->config);

        $this->assertFalse($jwt->isExpired($token));
        $this->assertNull($jwt->getTimeToExpiry($token));
    }

    public function testTryDecodeRethrowsConfigurationError(): void
    {
        $jwt   = new JWT($this->config);
        $token = $jwt->encode('payload');

        // A valid token, but the verifier is misconfigured (no SignedWith).
        // A config error must surface, not be masqueraded as an invalid token.
        $this->config->validateClaims = ['IssuedBy', 'LooseValidAt'];
        $verifier                     = new JWT($this->config);

        $this->expectException(JWTConfigurationException::class);
        $verifier->tryDecode($token);
    }

    public function testIsValidRethrowsConfigurationError(): void
    {
        $jwt   = new JWT($this->config);
        $token = $jwt->encode('payload');

        $this->config->validateClaims = ['SignedWith', 'NotARealConstraint'];
        $verifier                     = new JWT($this->config);

        $this->expectException(JWTConfigurationException::class);
        $verifier->isValid($token);
    }

    public function testTryDecodeStillReturnsNullForTokenFailures(): void
    {
        $jwt   = new JWT($this->config);
        $token = $jwt->encode('payload');

        // Token-level failures (tampered / malformed) stay swallowed.
        $this->assertNotInstanceOf(Plain::class, $jwt->tryDecode($this->mutateSignature($token)));
        $this->assertNotInstanceOf(Plain::class, $jwt->tryDecode('not-a-jwt'));
    }

    public function testWithParamDataRejectsReservedClaimName(): void
    {
        $jwt = new JWT($this->config);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('reserved');
        $jwt->withParamData('uid');
    }

    public function testSplitModeRejectsRegisteredClaimWithConfigException(): void
    {
        $jwt = (new JWT($this->config))->withSplitData();

        $this->expectException(JWTConfigurationException::class);
        $this->expectExceptionMessage('reserved JWT claim');
        $jwt->encode(['iss' => 'evil', 'role' => 'admin']);
    }

    public function testDecodeRejectsExpiredToken(): void
    {
        $jwt   = new JWT($this->config);
        $token = $jwt->withExpiresAt('-1 hour')->encode('payload');

        $this->assertTrue($jwt->isExpired($token));
        $this->assertSame(0, $jwt->getTimeToExpiry($token));
        $this->assertFalse($jwt->isValid($token));
        $this->assertNotInstanceOf(Plain::class, $jwt->tryDecode($token));

        $this->expectException(RequiredConstraintsViolated::class);
        $jwt->decode($token);
    }

    public function testExpiredTokenIsRejectedWithoutLeewayButAcceptedWithin(): void
    {
        $jwt   = new JWT($this->config);
        $token = $jwt->withExpiresAt('-30 seconds')->encode('payload');

        // leeway 0 and leeway null both mean "no tolerance" → still expired.
        $this->assertNotInstanceOf(Plain::class, $jwt->withLeeway(0)->tryDecode($token));
        $this->assertNotInstanceOf(Plain::class, $jwt->withLeeway(null)->tryDecode($token));

        // A 5-minute leeway must accept a token that expired 30s ago — this is
        // the only assertion that proves the DateInterval is wired into LooseValidAt
        // (PT300S, not null/wrong magnitude).
        $decoded = $jwt->withLeeway(300)->decode($token);
        $this->assertSame('payload', $decoded->claims()->get('data'));
    }

    public function testFutureNotBeforeIsRejected(): void
    {
        $now   = new DateTimeImmutable();
        $token = $this->handBuildToken($now, $now->modify('+1 hour'), $now->modify('+2 hours'));
        $jwt   = new JWT($this->config);

        $this->expectException(RequiredConstraintsViolated::class);
        $jwt->decode($token);
    }

    public function testFutureNotBeforeIsAcceptedWithLargeLeeway(): void
    {
        $now   = new DateTimeImmutable();
        $token = $this->handBuildToken($now, $now->modify('+1 hour'), $now->modify('+2 hours'));

        $decoded = (new JWT($this->config))->withLeeway(7200)->decode($token);
        $this->assertSame('payload', $decoded->claims()->get('data'));
    }

    public function testStrictValidAtRejectsTokenMissingTimeClaims(): void
    {
        $token = $this->encodeTokenWithoutExpiry('payload');

        $this->config->validateClaims = ['SignedWith', 'StrictValidAt'];
        $strict                       = new JWT($this->config);

        $this->expectException(RequiredConstraintsViolated::class);
        $strict->decode($token);
    }

    public function testLooseValidAtAcceptsTokenMissingTimeClaims(): void
    {
        $token = $this->encodeTokenWithoutExpiry('payload');

        $this->config->validateClaims = ['SignedWith', 'LooseValidAt'];
        $loose                        = new JWT($this->config);

        $this->assertSame('payload', $loose->decode($token)->claims()->get('data'));
    }

    public function testTamperedPayloadFailsValidation(): void
    {
        $jwt   = new JWT($this->config);
        $token = $jwt->encode('payload', 'original-user');

        $this->expectException(RequiredConstraintsViolated::class);
        $jwt->decode($this->mutatePayload($token));
    }

    public function testEmptyStringUidIsOmitted(): void
    {
        $jwt   = new JWT($this->config);
        $token = $jwt->encode('payload', '');

        $this->assertFalse($jwt->decode($token)->claims()->has('uid'));
    }

    public function testGetPayloadDecodesCompactArrayUnderCustomParamData(): void
    {
        $jwt   = (new JWT($this->config))->withParamData('payload');
        $data  = ['a' => 1, 'b' => 'two'];
        $token = $jwt->encode($data);

        $this->assertSame($data, $jwt->getPayload($token));
    }

    private function encodeTokenWithoutExpiry(string $data): string
    {
        $configuration                    = $this->symmetricTestConfiguration();
        [$issuer, $audience, $identifier] = $this->requiredClaims();

        return $configuration->builder()
            ->issuedBy($issuer)
            ->permittedFor($audience)
            ->identifiedBy($identifier)
            ->issuedAt(new DateTimeImmutable())
            ->withClaim('data', $data)
            ->getToken($configuration->signer(), $configuration->signingKey())
            ->toString();
    }

    /**
     * Hand-build a signed token with explicit time claims, bypassing encode()'s
     * future-`nbf` clamp so decode()'s time constraints can be exercised directly.
     */
    private function handBuildToken(
        ?DateTimeImmutable $issuedAt = null,
        ?DateTimeImmutable $notBefore = null,
        ?DateTimeImmutable $expiresAt = null,
    ): string {
        $configuration                    = $this->symmetricTestConfiguration();
        [$issuer, $audience, $identifier] = $this->requiredClaims();
        $now                              = new DateTimeImmutable();

        $builder = $configuration->builder()
            ->issuedBy($issuer)
            ->permittedFor($audience)
            ->identifiedBy($identifier)
            ->issuedAt($issuedAt ?? $now)
            ->withClaim('data', 'payload');

        if ($notBefore instanceof DateTimeImmutable) {
            $builder = $builder->canOnlyBeUsedAfter($notBefore);
        }
        if ($expiresAt instanceof DateTimeImmutable) {
            $builder = $builder->expiresAt($expiresAt);
        }

        return $builder
            ->getToken($configuration->signer(), $configuration->signingKey())
            ->toString();
    }

    private function symmetricTestConfiguration(): Configuration
    {
        $signer     = $this->config->signer;
        $issuer     = $this->config->issuer;
        $audience   = $this->config->audience;
        $identifier = $this->config->identifier;

        if (
            $signer === null || $signer === ''
                             || $issuer === null || $issuer === ''
                             || $audience === null || $audience === ''
                             || $identifier === null || $identifier === ''
        ) {
            $this->fail('JWT test config is incomplete.');
        }

        return Configuration::forSymmetricSigner(
            new \Lcobucci\JWT\Signer\Hmac\Sha256(),
            InMemory::base64Encoded($signer),
        );
    }

    /**
     * @return array{non-empty-string, non-empty-string, non-empty-string} issuer, audience, identifier
     */
    private function requiredClaims(): array
    {
        $issuer     = $this->config->issuer;
        $audience   = $this->config->audience;
        $identifier = $this->config->identifier;

        if (
            $issuer === null || $issuer === ''
                             || $audience === null || $audience === ''
                             || $identifier === null || $identifier === ''
        ) {
            $this->fail('JWT test config is incomplete.');
        }

        return [$issuer, $audience, $identifier];
    }

    private function mutateSignature(string $token): string
    {
        $segments    = explode('.', $token);
        $first       = $segments[2][0];
        $segments[2] = ($first === 'X' ? 'Y' : 'X') . substr($segments[2], 1);

        return implode('.', $segments);
    }

    /**
     * Tamper with a claim in the payload segment without re-signing, so the
     * signature no longer matches the modified body.
     */
    private function mutatePayload(string $token): string
    {
        $segments       = explode('.', $token);
        $payload        = json_decode((string) base64_decode(strtr($segments[1], '-_', '+/'), true), true);
        $payload['uid'] = 'tampered-admin';
        $segments[1]    = rtrim(strtr(base64_encode((string) json_encode($payload)), '+/', '-_'), '=');

        return implode('.', $segments);
    }
}
