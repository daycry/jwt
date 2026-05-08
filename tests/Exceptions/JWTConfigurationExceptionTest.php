<?php

declare(strict_types=1);

namespace Tests\Exceptions;

use Daycry\JWT\Exceptions\JWTConfigurationException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @internal
 */
final class JWTConfigurationExceptionTest extends TestCase
{
    public function testIsRuntimeException(): void
    {
        $this->assertInstanceOf(RuntimeException::class, JWTConfigurationException::missingSigner());
    }

    public function testMissingSignerMessageMentionsCommandAndConfig(): void
    {
        $exception = JWTConfigurationException::missingSigner();

        $this->assertStringContainsString('jwt:key', $exception->getMessage());
        $this->assertStringContainsString('jwt.signer', $exception->getMessage());
        $this->assertStringContainsString('Daycry\\JWT\\Config\\JWT::$signer', $exception->getMessage());
    }

    public function testMissingClaimMessageNamesField(): void
    {
        $exception = JWTConfigurationException::missingClaim('issuer');

        $this->assertStringContainsString('"issuer"', $exception->getMessage());
        $this->assertStringContainsString('jwt.issuer', $exception->getMessage());
    }

    public function testInvalidAlgorithmTypeMessageQuotesValue(): void
    {
        $exception = JWTConfigurationException::invalidAlgorithmType('hybrid');

        $this->assertStringContainsString('"hybrid"', $exception->getMessage());
        $this->assertStringContainsString('algorithmType', $exception->getMessage());
    }

    public function testUnknownConstraintListsAllowedNames(): void
    {
        $exception = JWTConfigurationException::unknownConstraint('FooConstraint');

        $this->assertStringContainsString('"FooConstraint"', $exception->getMessage());
        $this->assertStringContainsString('SignedWith', $exception->getMessage());
        $this->assertStringContainsString('LooseValidAt', $exception->getMessage());
        $this->assertStringContainsString('StrictValidAt', $exception->getMessage());
    }
}
