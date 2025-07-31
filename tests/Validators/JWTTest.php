<?php

namespace Tests\Validators;

use CodeIgniter\Test\CIUnitTestCase;
use Daycry\JWT\Config\JWT as JWTConfig;
use Daycry\JWT\JWT;
use Lcobucci\JWT\Validation\RequiredConstraintsViolated;


class JWTTest extends CIUnitTestCase
{
    protected JWTConfig $config;
    protected JWT $library;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = config('JWT');
        $this->config->uid = "myUid";
        // Remove ValidAt constraint to avoid timing issues in tests
        $this->config->validateClaims = [
            'SignedWith',
            'IssuedBy', 
            'IdentifiedBy',
            'PermittedFor',
        ];
        $this->library = new JWT($this->config);
    }

    public function testJWTEncodeString()
    {
        $message = 'hello';
        $encode = $this->library->encode($message);

        $decode = $this->library->decode($encode);

        $this->assertEquals($message, $decode->get('data'));
    }

    public function testJWTEncodeStringWithCustomUid()
    {
        $message = 'hello';
        $uid = 'custom';
        $encode = $this->library->encode($message, $uid);

        $decode = $this->library->decode($encode);

        $this->assertEquals($message, $decode->get('data'));
        $this->assertEquals($uid, $decode->get('uid'));
    }

    public function testJWTEncodeStringWithUid()
    {
        $message = 'hello';
        $encode = $this->library->encode($message);

        $decode = $this->library->decode($encode);

        $this->assertEquals($message, $decode->get('data'));
        $this->assertEquals($this->config->uid, $decode->get('uid'));
    }

    public function testJWTEncodeStringDefaultConfig()
    {
        $this->library = new JWT();
        $message = 'hello';
        $encode = $this->library->encode($message);

        $decode = $this->library->decode($encode);

        $this->assertEquals($message, $decode->get('data'));
    }

    public function testJWTEncodeArrayWithoutSplit()
    {
        $message = array(1,4,6);
        $encode = $this->library->encode($message);
        $decode = $this->library->decode($encode);

        $this->assertEquals($message, \json_decode($decode->get('data')));

        $message = array('param' => '1', 'other' => 1234);
        $encode = $this->library->encode($message);
        $decode = $this->library->decode($encode);

        $this->assertEquals($message, \json_decode($decode->get('data'), true));
    }

    public function testJWTEncodeArrayWithSplit()
    {
        $this->library->setSplitData();
        $message = array('param' => '1', 'other' => 1234);
        $encode = $this->library->encode($message);
        $decode = $this->library->decode($encode);

        $this->assertEquals($message['param'], $decode->get('param'));
        $this->assertEquals($message['other'], $decode->get('other'));
    }

    public function testJWTDecodeErrorThowable()
    {
        $this->expectException( RequiredConstraintsViolated::class );

        $message = 'hello';
        $encode = $this->library->encode($message);

        $this->config = config('JWT');
        $this->config->identifier = 'another';
        $this->library = new JWT($this->config);

        $decode = $this->library->decode($encode);
    }

    public function testJWTDecodeErrorNoThowable()
    {
        $message = 'hello';
        $encode = $this->library->encode($message);

        $this->config = config('JWT');
        $this->config->identifier = 'another';
        $this->config->throwable = false;
        $this->library = new JWT($this->config);

        $decode = $this->library->decode($encode);

        $this->assertMatchesRegularExpression('/The token violates/i', $decode->getMessage());
    }

    public function testJWTValidationConstraints()
    {
        $message = 'test validation';
        $encode = $this->library->encode($message);

        // Test that all default validation constraints work
        $this->config->validateClaims = [
            'SignedWith',
            'IssuedBy', 
            'ValidAt',
            'IdentifiedBy',
            'PermittedFor',
        ];
        $this->config->validate = true;
        
        $library = new JWT($this->config);
        $decode = $library->decode($encode);

        $this->assertEquals($message, $decode->get('data'));
    }

    public function testJWTValidationDisabled()
    {
        $message = 'test no validation';
        $encode = $this->library->encode($message);

        // Test that validation can be disabled
        $this->config->validate = false;
        
        $library = new JWT($this->config);
        $decode = $library->decode($encode);

        $this->assertEquals($message, $decode->get('data'));
    }

    public function testJWTPartialValidationConstraints()
    {
        $message = 'test partial validation';
        $encode = $this->library->encode($message);

        // Test with only some constraints
        $this->config->validateClaims = [
            'SignedWith',
            'ValidAt',
        ];
        $this->config->validate = true;
        
        $library = new JWT($this->config);
        $decode = $library->decode($encode);

        $this->assertEquals($message, $decode->get('data'));
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
