<?php

declare (strict_types=1);
namespace RectorPrefix20220430;

use Rector\Config\RectorConfig;
use Rector\PHPUnit\Rector\MethodCall\GetMockBuilderGetMockToCreateMockRector;
use Rector\PHPUnit\Rector\MethodCall\RemoveExpectAnyFromMockRector;
use Rector\PHPUnit\Rector\MethodCall\RemoveSetMethodsMethodCallRector;
/**
 * Set to improve direct testing of your code, without mock overgrown weed everywhere. Make it simple and clear, easy to
 * maintain and swift to read.
 *
 * @see https://blog.frankdejonge.nl/testing-without-mocking-frameworks/
 * @see https://maksimivanov.com/posts/dont-mock-what-you-dont-own/
 * @see https://dev.to/mguinea/stop-using-mocking-libraries-2f2k
 * @see https://mnapoli.fr/anonymous-classes-in-tests/
 * @see https://steemit.com/php/@crell/don-t-use-mocking-libraries
 * @see https://davegebler.com/post/php/better-php-unit-testing-avoiding-mocks
 */
return static function (\Rector\Config\RectorConfig $rectorConfig) : void {
    $rectorConfig->rule(\Rector\PHPUnit\Rector\MethodCall\RemoveSetMethodsMethodCallRector::class);
    $rectorConfig->rule(\Rector\PHPUnit\Rector\MethodCall\GetMockBuilderGetMockToCreateMockRector::class);
    $rectorConfig->rule(\Rector\PHPUnit\Rector\MethodCall\RemoveExpectAnyFromMockRector::class);
};
