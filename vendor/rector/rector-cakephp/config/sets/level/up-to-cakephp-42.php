<?php

declare (strict_types=1);
namespace RectorPrefix20220517;

use Rector\CakePHP\Set\CakePHPLevelSetList;
use Rector\CakePHP\Set\CakePHPSetList;
use Rector\Config\RectorConfig;
return static function (\Rector\Config\RectorConfig $rectorConfig) : void {
    $rectorConfig->sets([\Rector\CakePHP\Set\CakePHPSetList::CAKEPHP_42, \Rector\CakePHP\Set\CakePHPLevelSetList::UP_TO_CAKEPHP_41]);
};
