<?php

declare (strict_types=1);
namespace Rector\Core\ValueObject;

use Rector\ChangesReporting\Output\ConsoleOutputFormatter;
final class Configuration
{
    /**
     * @param string[] $fileExtensions
     * @param string[] $paths
     */
    public function __construct(
        /**
         * @readonly
         */
        private readonly bool $isDryRun = \false,
        /**
         * @readonly
         */
        private readonly bool $showProgressBar = \true,
        /**
         * @readonly
         */
        private readonly bool $shouldClearCache = \false,
        /**
         * @readonly
         */
        private readonly string $outputFormat = ConsoleOutputFormatter::NAME,
        /**
         * @readonly
         */
        private readonly array $fileExtensions = ['php'],
        /**
         * @readonly
         */
        private readonly array $paths = [],
        /**
         * @readonly
         */
        private readonly bool $showDiffs = \true,
        /**
         * @readonly
         */
        private readonly ?string $parallelPort = null,
        /**
         * @readonly
         */
        private readonly ?string $parallelIdentifier = null,
        /**
         * @readonly
         */
        private readonly bool $isParallel = \false,
        /**
         * @readonly
         */
        private readonly ?string $memoryLimit = null
    )
    {
    }
    public function isDryRun() : bool
    {
        return $this->isDryRun;
    }
    public function shouldShowProgressBar() : bool
    {
        return $this->showProgressBar;
    }
    public function shouldClearCache() : bool
    {
        return $this->shouldClearCache;
    }
    /**
     * @return string[]
     */
    public function getFileExtensions() : array
    {
        return $this->fileExtensions;
    }
    /**
     * @return string[]
     */
    public function getPaths() : array
    {
        return $this->paths;
    }
    public function getOutputFormat() : string
    {
        return $this->outputFormat;
    }
    public function shouldShowDiffs() : bool
    {
        return $this->showDiffs;
    }
    public function getParallelPort() : ?string
    {
        return $this->parallelPort;
    }
    public function getParallelIdentifier() : ?string
    {
        return $this->parallelIdentifier;
    }
    public function isParallel() : bool
    {
        return $this->isParallel;
    }
    public function getMemoryLimit() : ?string
    {
        return $this->memoryLimit;
    }
}
