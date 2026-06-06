<?php

declare(strict_types=1);

namespace Daycry\JWT\Enums;

/**
 * Signing algorithm family for `Config\JWT::$algorithmType`.
 *
 * Drives how `signer` / `signingKey` / `verifyingKey` are interpreted. The config
 * property stays a plain string (for `.env` / BaseConfig compatibility); it is
 * resolved through `tryFrom()` at build time.
 */
enum AlgorithmType: string
{
    case Symmetric  = 'symmetric';
    case Asymmetric = 'asymmetric';
}
