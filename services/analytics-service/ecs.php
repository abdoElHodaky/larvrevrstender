<?php

declare(strict_types=1);

use Symplify\EasyCodingStandard\Config\ECSConfig;
use Symplify\EasyCodingStandard\ValueObject\Set\SetList;

return ECSConfig::configure()
    ->withPaths([
        __DIR__ . "/app",
        __DIR__ . "/config",
        __DIR__ . "/database",
        __DIR__ . "/routes",
        __DIR__ . "/tests",
    ])
    ->withSkip([
        __DIR__ . "/vendor",
        __DIR__ . "/storage",
        __DIR__ . "/bootstrap/cache",
        __DIR__ . "/node_modules",
    ])
    ->withSets([
        SetList::CLEAN_CODE,
        SetList::PSR_12,
        SetList::COMMON,
        SetList::SYMPLIFY,
    ])
    ->withRules([
        // Add specific rules for Laravel/PHP 8.3 modernization
        \PhpCsFixer\Fixer\Strict\DeclareStrictTypesFixer::class,
        \PhpCsFixer\Fixer\ClassNotation\ClassAttributesSeparationFixer::class,
        \PhpCsFixer\Fixer\FunctionNotation\ReturnTypeDeclarationFixer::class,
        \PhpCsFixer\Fixer\Phpdoc\PhpdocTypesOrderFixer::class,
    ]);
