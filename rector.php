<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\BooleanAnd\RemoveAndTrueRector;
use Rector\DeadCode\Rector\ClassConst\RemoveUnusedPrivateClassConstantRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPrivateMethodParameterRector;
use Rector\DeadCode\Rector\Stmt\RemoveUnreachableStatementRector;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([__DIR__.'/src']);
    $rectorConfig->skip([__DIR__.'/tests/*']);

    $rectorConfig->rule(RemoveAndTrueRector::class);
    $rectorConfig->rule(RemoveUnreachableStatementRector::class);
    $rectorConfig->rule(RemoveUnusedPrivateClassConstantRector::class);
    $rectorConfig->rule(RemoveUnusedPrivateMethodParameterRector::class);
};
