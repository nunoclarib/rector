<?php

declare (strict_types=1);
namespace RectorPrefix20220610\Symplify\SymplifyKernel\Tests\ContainerBuilderFactory;

use PHPUnit\Framework\TestCase;
use RectorPrefix20220610\Symplify\SmartFileSystem\SmartFileSystem;
use RectorPrefix20220610\Symplify\SymplifyKernel\Config\Loader\ParameterMergingLoaderFactory;
use RectorPrefix20220610\Symplify\SymplifyKernel\ContainerBuilderFactory;
final class ContainerBuilderFactoryTest extends TestCase
{
    public function test() : void
    {
        $containerBuilderFactory = new ContainerBuilderFactory(new ParameterMergingLoaderFactory());
        $containerBuilder = $containerBuilderFactory->create([__DIR__ . '/config/some_services.php'], [], []);
        $hasSmartFileSystemService = $containerBuilder->has(SmartFileSystem::class);
        $this->assertTrue($hasSmartFileSystemService);
    }
}
