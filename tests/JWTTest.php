<?php

namespace Tests\Validators;

use CodeIgniter\Test\CIUnitTestCase;
use Codeigniter\Config\BaseConfig;

class JWTTest extends CIUnitTestCase
{
    protected BaseConfig $config;
    protected \Daycry\JWT\JWT $library;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = config('JWT');
        $this->config->uid = "myUid";
        $this->library = new \Daycry\JWT\JWT($this->config);
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
        $this->library = new \Daycry\JWT\JWT();
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
        $this->expectException( \Lcobucci\JWT\Validation\RequiredConstraintsViolated::class );

        $message = 'hello';
        $encode = $this->library->encode($message);

        $this->config = config('JWT');
        $this->config->identifier = 'another';
        $this->library = new \Daycry\JWT\JWT($this->config);

        $decode = $this->library->decode($encode);
    }

    public function testJWTDecodeErrorNoThowable()
    {
        $message = 'hello';
        $encode = $this->library->encode($message);

        $this->config = config('JWT');
        $this->config->identifier = 'another';
        $this->config->throwable = false;
        $this->library = new \Daycry\JWT\JWT($this->config);

        $decode = $this->library->decode($encode);

        $this->assertMatchesRegularExpression('/The token violates/i', $decode->getMessage());
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
