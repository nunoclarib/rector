<?php

declare (strict_types=1);
namespace Rector\Core\Kernel;

use Rector\Core\Config\Loader\ConfigureCallMergingLoaderFactory;
use RectorPrefix202209\Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use RectorPrefix202209\Symfony\Component\DependencyInjection\ContainerBuilder;
use RectorPrefix202209\Webmozart\Assert\Assert;
final class ContainerBuilderFactory
{
    public function __construct(
        /**
         * @readonly
         */
        private readonly ConfigureCallMergingLoaderFactory $configureCallMergingLoaderFactory
    )
    {
    }
    /**
     * @param string[] $configFiles
     * @param CompilerPassInterface[] $compilerPasses
     */
    public function create(array $configFiles, array $compilerPasses) : ContainerBuilder
    {
        Assert::allIsAOf($compilerPasses, CompilerPassInterface::class);
        Assert::allString($configFiles);
        Assert::allFile($configFiles);
        $containerBuilder = new ContainerBuilder();
        $this->registerConfigFiles($containerBuilder, $configFiles);
        foreach ($compilerPasses as $compilerPass) {
            $containerBuilder->addCompilerPass($compilerPass);
        }
        return $containerBuilder;
    }
    /**
     * @param string[] $configFiles
     */
    private function registerConfigFiles(ContainerBuilder $containerBuilder, array $configFiles) : void
    {
        $delegatingLoader = $this->configureCallMergingLoaderFactory->create($containerBuilder, \getcwd());
        foreach ($configFiles as $configFile) {
            $delegatingLoader->load($configFile);
        }
    }
}
