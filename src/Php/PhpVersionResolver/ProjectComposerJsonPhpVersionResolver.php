<?php

declare (strict_types=1);
namespace Rector\Core\Php\PhpVersionResolver;

use RectorPrefix202209\Composer\Semver\VersionParser;
use RectorPrefix202209\Nette\Utils\FileSystem;
use RectorPrefix202209\Nette\Utils\Json;
use Rector\Core\Util\PhpVersionFactory;
/**
 * @see \Rector\Core\Tests\Php\PhpVersionResolver\ProjectComposerJsonPhpVersionResolver\ProjectComposerJsonPhpVersionResolverTest
 */
final class ProjectComposerJsonPhpVersionResolver
{
    public function __construct(
        /**
         * @readonly
         */
        private readonly VersionParser $versionParser,
        /**
         * @readonly
         */
        private readonly PhpVersionFactory $phpVersionFactory
    )
    {
    }
    public function resolve(string $composerJson) : ?int
    {
        $composerJsonContents = FileSystem::read($composerJson);
        $projectComposerJson = Json::decode($composerJsonContents, Json::FORCE_ARRAY);
        // see https://getcomposer.org/doc/06-config.md#platform
        $platformPhp = $projectComposerJson['config']['platform']['php'] ?? null;
        if ($platformPhp !== null) {
            return $this->phpVersionFactory->createIntVersion($platformPhp);
        }
        $requirePhpVersion = $projectComposerJson['require']['php'] ?? null;
        if ($requirePhpVersion === null) {
            return null;
        }
        return $this->createIntVersionFromComposerVersion($requirePhpVersion);
    }
    private function createIntVersionFromComposerVersion(string $projectPhpVersion) : int
    {
        $constraint = $this->versionParser->parseConstraints($projectPhpVersion);
        $lowerBound = $constraint->getLowerBound();
        $lowerBoundVersion = $lowerBound->getVersion();
        return $this->phpVersionFactory->createIntVersion($lowerBoundVersion);
    }
}
