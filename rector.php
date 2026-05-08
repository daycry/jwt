<?php

declare(strict_types=1);

use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\Config\RectorConfig;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withSets([
        SetList::DEAD_CODE,
        SetList::CODE_QUALITY,
        SetList::EARLY_RETURN,
        LevelSetList::UP_TO_PHP_82,
        PHPUnitSetList::PHPUNIT_CODE_QUALITY,
        PHPUnitSetList::PHPUNIT_100,
    ])
    ->withParallel()
    ->withCache(
        cacheClass: FileCacheStorage::class,
        cacheDirectory: is_dir('/tmp') ? '/tmp/rector' : __DIR__ . '/build/rector',
    )
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withAutoloadPaths([
        __DIR__ . '/vendor/autoload.php',
    ])
    ->withBootstrapFiles([
        __DIR__ . '/vendor/codeigniter4/framework/system/Test/bootstrap.php',
    ])
    ->withPhpVersion(PhpVersion::PHP_82)
    ->withImportNames(importShortClasses: false, removeUnusedImports: true)
    ->withSkip([
        // Examples are documentation, not production code.
        __DIR__ . '/examples',
    ]);
