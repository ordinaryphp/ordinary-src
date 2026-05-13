<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\DeclareStrictTypesRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/packages',
    ])
    ->withSkip([
        '*/vendor/*',
        '*/tests/Fixtures/*',
    ])
    ->withPhpSets(php85: true)
    ->withRules([
        DeclareStrictTypesRector::class,
    ])
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        privatization: true,
        earlyReturn: true,
        instanceof: true,
    )
    ->withImportNames(importShortClasses: false, removeUnusedImports: true);
