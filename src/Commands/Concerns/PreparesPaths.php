<?php

declare(strict_types=1);

namespace Daycry\JWT\Commands\Concerns;

use RuntimeException;

/**
 * Shared filesystem helper for the JWT commands.
 */
trait PreparesPaths
{
    /**
     * Create a directory (recursively) if it does not already exist.
     *
     * The mode is explicit per call site on purpose — 0700 for the private-key
     * directory, 0777 for the published-config directory — and must not be
     * collapsed into a single shared default, since the difference is a deliberate
     * security distinction.
     *
     * @throws RuntimeException When the directory cannot be created.
     */
    protected function ensureDirectory(string $directory, int $mode): void
    {
        if (! is_dir($directory) && ! mkdir($directory, $mode, true) && ! is_dir($directory)) {
            throw new RuntimeException("Cannot create directory: {$directory}");
        }
    }
}
