<?php

declare(strict_types=1);

namespace Daycry\JWT\Exceptions;

use RuntimeException;

/**
 * Thrown by `JWT::decode()` when the token cannot be parsed (malformed, wrong type, etc.).
 *
 * For validation failures (signature, claims), `decode()` lets the underlying
 * `Lcobucci\JWT\Validation\RequiredConstraintsViolated` propagate unchanged so callers
 * can inspect the violations.
 */
class InvalidTokenException extends RuntimeException
{
}
