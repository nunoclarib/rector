<?php

declare (strict_types=1);
namespace Rector\Core\DependencyInjection;

use RectorPrefix20220517\Nette\Utils\FileSystem;
use RectorPrefix20220517\Psr\Container\ContainerInterface;
use Rector\Caching\Detector\ChangedFilesDetector;
use Rector\Core\Kernel\RectorKernel;
use Rector\Core\ValueObject\Bootstrap\BootstrapConfigs;
use RectorPrefix20220517\Symfony\Component\Console\Style\SymfonyStyle;
final class RectorContainerFactory
{
    public function createFromBootstrapConfigs(\Rector\Core\ValueObject\Bootstrap\BootstrapConfigs $bootstrapConfigs) : \RectorPrefix20220517\Psr\Container\ContainerInterface
    {
        $container = $this->createFromConfigs($bootstrapConfigs->getConfigFiles());
        $mainConfigFile = $bootstrapConfigs->getMainConfigFile();
        if ($mainConfigFile !== null) {
            // warning about old syntax before RectorConfig
            $fileContents = \RectorPrefix20220517\Nette\Utils\FileSystem::read($mainConfigFile);
            if (\strpos($fileContents, 'ContainerConfigurator $containerConfigurator') !== \false) {
                /** @var SymfonyStyle $symfonyStyle */
                $symfonyStyle = $container->get(\RectorPrefix20220517\Symfony\Component\Console\Style\SymfonyStyle::class);
                // @todo add link to blog post after release
                $warningMessage = \sprintf('Your "%s" config is using old syntax with "ContainerConfigurator".%sUpgrade to "RectorConfig" that allows better autocomplete and future standard.', $mainConfigFile, \PHP_EOL);
                $symfonyStyle->warning($warningMessage);
                // to make message noticable
                \sleep(3);
            }
            /** @var ChangedFilesDetector $changedFilesDetector */
            $changedFilesDetector = $container->get(\Rector\Caching\Detector\ChangedFilesDetector::class);
            $changedFilesDetector->setFirstResolvedConfigFileInfo($mainConfigFile);
        }
        return $container;
    }
    /**
     * @param string[] $configFiles
     * @api
     */
    private function createFromConfigs(array $configFiles) : \RectorPrefix20220517\Psr\Container\ContainerInterface
    {
        $rectorKernel = new \Rector\Core\Kernel\RectorKernel();
        return $rectorKernel->createFromConfigs($configFiles);
    }
}
