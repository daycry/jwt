<?php

declare(strict_types=1);

namespace Tests\Validators;

use CodeIgniter\Test\CIUnitTestCase;
use Daycry\JWT\Config\JWT as JWTConfig;
use Daycry\JWT\JWT;
use InvalidArgumentException;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Validation\RequiredConstraintsViolated;

/**
 * Coverage for the 3.2.0 immutable customisers and validated reads.
 *
 * @internal
 */
final class ApiCustomisersTest extends CIUnitTestCase
{
    private JWTConfig $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config      = config('JWT');
        $this->config->uid = 'api-test';
    }

    public function testWithIssuerOverrideRoundTrips(): void
    {
        $jwt   = (new JWT($this->config))->withIssuer('https://override-issuer.example');
        $token = $jwt->encode('payload');

        $decoded = $jwt->decode($token);
        $this->assertSame('https://override-issuer.example', $decoded->claims()->get('iss'));
    }

    public function testWithIdentifierOverrideRoundTrips(): void
    {
        $jwt   = (new JWT($this->config))->withIdentifier('override-jti');
        $token = $jwt->encode('payload');

        $this->assertSame('override-jti', $jwt->decode($token)->claims()->get('jti'));
    }

    public function testWithAudienceSupportsMultipleAudiences(): void
    {
        $jwt   = (new JWT($this->config))->withAudience('https://a.example', 'https://b.example');
        $token = $jwt->encode('payload');

        $decoded = $jwt->decode($token);
        $aud     = (array) $decoded->claims()->get('aud');

        $this->assertContains('https://a.example', $aud);
        $this->assertContains('https://b.example', $aud);
    }

    public function testWithIssuerMismatchFailsValidation(): void
    {
        $token = (new JWT($this->config))->withIssuer('https://issuer-a.example')->encode('payload');

        $verifier = (new JWT($this->config))->withIssuer('https://issuer-b.example');

        $this->expectException(RequiredConstraintsViolated::class);
        $verifier->decode($token);
    }

    public function testWithIssuerRejectsEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new JWT($this->config))->withIssuer('');
    }

    public function testWithAudienceRejectsEmptyList(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new JWT($this->config))->withAudience();
    }

    public function testWithKeyIdWritesKidHeader(): void
    {
        $jwt   = (new JWT($this->config))->withKeyId('key-2026');
        $token = $jwt->encode('payload');

        $this->assertSame('key-2026', $jwt->decode($token)->headers()->get('kid'));
    }

    public function testKidSelectsVerificationKeyFromMap(): void
    {
        // The rotation map points kid=kA at a WRONG secret, so a token tagged
        // kid=kA must fail verification — proving the kid selects the map key
        // over the (correct) default signer secret.
        $this->config->verifyingKeys = ['kA' => base64_encode(random_bytes(32))];
        $jwt                         = (new JWT($this->config))->withKeyId('kA');

        $token = $jwt->encode('payload');

        $this->assertNotInstanceOf(Plain::class, $jwt->tryDecode($token));
    }

    public function testTokenWithoutKidUsesDefaultKey(): void
    {
        // Same wrong-key map, but no kid header → default secret is used → valid.
        $this->config->verifyingKeys = ['kA' => base64_encode(random_bytes(32))];
        $jwt                         = new JWT($this->config);

        $token = $jwt->encode('payload');

        $this->assertSame('payload', $jwt->decode($token)->claims()->get('data'));
    }

    public function testWithHeaderWritesCustomHeader(): void
    {
        $jwt   = (new JWT($this->config))->withHeader('x-custom', 'value-1');
        $token = $jwt->encode('payload');

        $this->assertSame('value-1', $jwt->decode($token)->headers()->get('x-custom'));
    }

    public function testWithHeaderRejectsReservedCty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new JWT($this->config))->withHeader('cty', 'json');
    }

    public function testWithClaimsAddsTopLevelClaims(): void
    {
        $jwt   = (new JWT($this->config))->withClaims(['scope' => 'admin', 'tenant' => 'acme']);
        $token = $jwt->encode('payload');

        $decoded = $jwt->decode($token);
        $this->assertSame('admin', $decoded->claims()->get('scope'));
        $this->assertSame('acme', $decoded->claims()->get('tenant'));
    }

    public function testWithClaimsRejectsReservedName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new JWT($this->config))->withClaims(['uid' => 'x']);
    }

    public function testGetClaimsReturnsAllValidatedClaims(): void
    {
        $jwt    = new JWT($this->config);
        $token  = $jwt->encode('payload', 'user-7');
        $claims = $jwt->getClaims($token);

        $this->assertSame('user-7', $claims['uid']);
        $this->assertArrayHasKey('iss', $claims);
        $this->assertArrayHasKey('exp', $claims);
    }

    public function testGetClaimReturnsSingleClaim(): void
    {
        $jwt   = new JWT($this->config);
        $token = $jwt->encode('payload', 'user-9');

        $this->assertSame('user-9', $jwt->getClaim($token, 'uid'));
        $this->assertNull($jwt->getClaim($token, 'does-not-exist'));
    }

    public function testGetClaimsThrowsOnTamperedToken(): void
    {
        $jwt   = new JWT($this->config);
        $token = $jwt->encode('payload');

        $segments    = explode('.', $token);
        $segments[2] = ($segments[2][0] === 'X' ? 'Y' : 'X') . substr($segments[2], 1);

        $this->expectException(RequiredConstraintsViolated::class);
        $jwt->getClaims(implode('.', $segments));
    }

    public function testCustomisersReturnNewInstances(): void
    {
        $base = new JWT($this->config);

        $this->assertNotSame($base, $base->withIssuer('x'));
        $this->assertNotSame($base, $base->withAudience('x'));
        $this->assertNotSame($base, $base->withIdentifier('x'));
        $this->assertNotSame($base, $base->withKeyId('x'));
        $this->assertNotSame($base, $base->withHeader('x', '1'));
        $this->assertNotSame($base, $base->withClaims(['x' => '1']));
    }
}
