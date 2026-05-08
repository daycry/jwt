<?php

namespace Tests\Validators;

use CodeIgniter\Test\CIUnitTestCase;
use Daycry\JWT\Config\JWT as JWTConfig;
use Daycry\JWT\Exceptions\InvalidTokenException;
use Daycry\JWT\JWT;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Validation\RequiredConstraintsViolated;

/**
 * @internal
 */
final class JWTTest extends CIUnitTestCase
{
    private JWTConfig $config;
    private JWT $library;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config      = config('JWT');
        $this->config->uid = 'myUid';
        $this->library     = new JWT($this->config);
    }

    public function testJWTEncodeString(): void
    {
        $token   = $this->library->encode('hello');
        $decoded = $this->library->decode($token);

        $this->assertInstanceOf(Plain::class, $decoded);
        $this->assertSame('hello', $decoded->claims()->get('data'));
    }

    public function testJWTEncodeStringWithCustomUid(): void
    {
        $token   = $this->library->encode('hello', 'custom');
        $decoded = $this->library->decode($token);

        $this->assertSame('hello', $decoded->claims()->get('data'));
        $this->assertSame('custom', $decoded->claims()->get('uid'));
    }

    public function testJWTEncodeStringPicksUpDefaultUid(): void
    {
        $token   = $this->library->encode('hello');
        $decoded = $this->library->decode($token);

        $this->assertSame('myUid', $decoded->claims()->get('uid'));
    }

    public function testJWTEncodeStringDefaultConfig(): void
    {
        $jwt     = JWT::for();
        $token   = $jwt->encode('hello');
        $decoded = $jwt->decode($token);

        $this->assertSame('hello', $decoded->claims()->get('data'));
    }

    public function testJWTEncodeArrayWithoutSplit(): void
    {
        $payload = [1, 4, 6];
        $token   = $this->library->encode($payload);
        $decoded = $this->library->decode($token);

        $this->assertSame($payload, json_decode((string) $decoded->claims()->get('data')));

        $payload = ['param' => '1', 'other' => 1234];
        $token   = $this->library->encode($payload);
        $decoded = $this->library->decode($token);

        $this->assertSame($payload, json_decode((string) $decoded->claims()->get('data'), true));
    }

    public function testJWTEncodeArrayWithSplit(): void
    {
        $jwt     = $this->library->withSplitData();
        $payload = ['param' => '1', 'other' => 1234];
        $token   = $jwt->encode($payload);
        $decoded = $jwt->decode($token);

        $this->assertSame('1', $decoded->claims()->get('param'));
        $this->assertSame(1234, $decoded->claims()->get('other'));
    }

    public function testDecodeThrowsOnWrongIdentifier(): void
    {
        $token = $this->library->encode('hello');

        $this->config->identifier = 'another';
        $verifier                 = new JWT($this->config);

        $this->expectException(RequiredConstraintsViolated::class);
        $verifier->decode($token);
    }

    public function testTryDecodeReturnsNullOnFailure(): void
    {
        $token = $this->library->encode('hello');

        $this->config->identifier = 'another';
        $verifier                 = new JWT($this->config);

        $this->assertNull($verifier->tryDecode($token));
    }

    public function testJWTValidationConstraintsAllPass(): void
    {
        $token = $this->library->encode('test validation');

        $this->config->validateClaims = [
            'SignedWith',
            'IssuedBy',
            'LooseValidAt',
            'IdentifiedBy',
            'PermittedFor',
        ];
        $library = new JWT($this->config);
        $decoded = $library->decode($token);

        $this->assertSame('test validation', $decoded->claims()->get('data'));
    }

    public function testJWTValidationDisabled(): void
    {
        $token = $this->library->encode('test no validation');

        $this->config->validate = false;
        $library                = new JWT($this->config);
        $decoded                = $library->decode($token);

        $this->assertSame('test no validation', $decoded->claims()->get('data'));
    }

    public function testJWTPartialValidationConstraints(): void
    {
        $token = $this->library->encode('test partial validation');

        $this->config->validateClaims = ['SignedWith', 'LooseValidAt'];
        $library                      = new JWT($this->config);
        $decoded                      = $library->decode($token);

        $this->assertSame('test partial validation', $decoded->claims()->get('data'));
    }

    public function testWithParamDataReturnsNewInstance(): void
    {
        $first  = $this->library;
        $second = $this->library->withParamData('payload');

        $this->assertNotSame($first, $second);
        $this->assertSame('data', $first->getParamData());
        $this->assertSame('payload', $second->getParamData());
    }

    public function testInvalidTokenThrowsInvalidTokenException(): void
    {
        $this->expectException(InvalidTokenException::class);
        $this->library->decode('not-a-jwt');
    }

    public function testWithLeewayRejectsNegativeValues(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->library->withLeeway(-1);
    }

    public function testWithParamDataRejectsEmptyName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->library->withParamData('');
    }

    public function testIsSplitDataTracksState(): void
    {
        $this->assertFalse($this->library->isSplitData());
        $this->assertTrue($this->library->withSplitData()->isSplitData());
        $this->assertFalse($this->library->withSplitData(false)->isSplitData());
    }
}
