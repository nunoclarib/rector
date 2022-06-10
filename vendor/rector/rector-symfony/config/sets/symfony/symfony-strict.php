<?php

declare (strict_types=1);
namespace RectorPrefix20220610;

use Rector\Config\RectorConfig;
use Rector\Symfony\Rector\ClassMethod\RenderMethodParamToTypeDeclarationRector;
return static function (RectorConfig $rectorConfig) : void {
    $rectorConfig->rule(RenderMethodParamToTypeDeclarationRector::class);
};
