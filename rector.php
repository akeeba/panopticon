<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/ViewTemplates',
        __DIR__ . '/assets',
        __DIR__ . '/cli',
        __DIR__ . '/includes',
        __DIR__ . '/src',
        __DIR__ . '/user_code',
    ])
	->withPhpLevel(85)
	->withSets([
		SetList::PHP_70, SetList::PHP_71, SetList::PHP_72, SetList::PHP_73, SetList::PHP_74,
		SetList::PHP_80, SetList::PHP_81, SetList::PHP_82, SetList::PHP_83, SetList::PHP_84, SetList::PHP_85,
		])
    ->withTypeCoverageLevel(0)
    ->withDeadCodeLevel(0)
    ->withCodeQualityLevel(0);
