<?php

declare(strict_types=1);

namespace Daycry\JWT\Enums;

/**
 * Validation constraints allowed in `Config\JWT::$validateClaims`.
 *
 * The config keeps plain strings; they are resolved through {@see self::fromName()}
 * so the legacy `ValidAt` alias keeps mapping to {@see self::LooseValidAt}. The
 * allowed-name list in exception messages is generated from {@see self::names()},
 * so it can never drift from the cases handled here.
 */
enum ConstraintName: string
{
    case SignedWith    = 'SignedWith';
    case IssuedBy      = 'IssuedBy';
    case IdentifiedBy  = 'IdentifiedBy';
    case PermittedFor  = 'PermittedFor';
    case StrictValidAt = 'StrictValidAt';
    case LooseValidAt  = 'LooseValidAt';

    /**
     * Legacy alias kept for backward compatibility.
     */
    public const ALIAS_VALID_AT = 'ValidAt';

    /**
     * Resolve a constraint name, normalising the legacy `ValidAt` alias to
     * `LooseValidAt`. Returns null for an unknown name.
     */
    public static function fromName(string $name): ?self
    {
        if ($name === self::ALIAS_VALID_AT) {
            return self::LooseValidAt;
        }

        return self::tryFrom($name);
    }

    /**
     * Canonical constraint names, for documentation and error messages.
     *
     * @return list<string>
     */
    public static function names(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
