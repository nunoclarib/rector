<?php

declare (strict_types=1);
namespace Rector\Core\Application\FileSystem;

use Rector\Core\Contract\Console\OutputStyleInterface;
use Rector\Core\FileSystem\FilePathHelper;
use Rector\Core\PhpParser\Printer\NodesWithFileDestinationPrinter;
use Rector\Core\ValueObject\Configuration;
use RectorPrefix202209\Symfony\Component\Filesystem\Filesystem;
/**
 * Adds and removes scheduled file
 */
final class RemovedAndAddedFilesProcessor
{
    public function __construct(
        /**
         * @readonly
         */
        private readonly Filesystem $filesystem,
        /**
         * @readonly
         */
        private readonly NodesWithFileDestinationPrinter $nodesWithFileDestinationPrinter,
        /**
         * @readonly
         */
        private readonly \Rector\Core\Application\FileSystem\RemovedAndAddedFilesCollector $removedAndAddedFilesCollector,
        /**
         * @readonly
         */
        private readonly OutputStyleInterface $rectorOutputStyle,
        /**
         * @readonly
         */
        private readonly FilePathHelper $filePathHelper
    )
    {
    }
    public function run(Configuration $configuration) : void
    {
        $this->processAddedFilesWithContent($configuration);
        $this->processAddedFilesWithNodes($configuration);
        $this->processMovedFilesWithNodes($configuration);
        $this->processDeletedFiles($configuration);
    }
    private function processDeletedFiles(Configuration $configuration) : void
    {
        foreach ($this->removedAndAddedFilesCollector->getRemovedFiles() as $removedFilePath) {
            $removedFileRelativePath = $this->filePathHelper->relativePath($removedFilePath);
            // @todo file helper
            //            $removedFileRelativePath = $removedFile->getRelativeFilePathFromDirectory(getcwd());
            if ($configuration->isDryRun()) {
                $message = \sprintf('File "%s" will be removed', $removedFileRelativePath);
                $this->rectorOutputStyle->warning($message);
            } else {
                $message = \sprintf('File "%s" was removed', $removedFileRelativePath);
                $this->rectorOutputStyle->warning($message);
                $this->filesystem->remove($removedFilePath);
            }
        }
    }
    private function processAddedFilesWithContent(Configuration $configuration) : void
    {
        foreach ($this->removedAndAddedFilesCollector->getAddedFilesWithContent() as $addedFileWithContent) {
            if ($configuration->isDryRun()) {
                $message = \sprintf('File "%s" will be added', $addedFileWithContent->getFilePath());
                $this->rectorOutputStyle->note($message);
            } else {
                $this->filesystem->dumpFile($addedFileWithContent->getFilePath(), $addedFileWithContent->getFileContent());
                $message = \sprintf('File "%s" was added', $addedFileWithContent->getFilePath());
                $this->rectorOutputStyle->note($message);
            }
        }
    }
    private function processAddedFilesWithNodes(Configuration $configuration) : void
    {
        foreach ($this->removedAndAddedFilesCollector->getAddedFilesWithNodes() as $addedFileWithNode) {
            $fileContent = $this->nodesWithFileDestinationPrinter->printNodesWithFileDestination($addedFileWithNode);
            if ($configuration->isDryRun()) {
                $message = \sprintf('File "%s" will be added', $addedFileWithNode->getFilePath());
                $this->rectorOutputStyle->note($message);
            } else {
                $this->filesystem->dumpFile($addedFileWithNode->getFilePath(), $fileContent);
                $message = \sprintf('File "%s" was added', $addedFileWithNode->getFilePath());
                $this->rectorOutputStyle->note($message);
            }
        }
    }
    private function processMovedFilesWithNodes(Configuration $configuration) : void
    {
        foreach ($this->removedAndAddedFilesCollector->getMovedFiles() as $movedFile) {
            $fileContent = $this->nodesWithFileDestinationPrinter->printNodesWithFileDestination($movedFile);
            if ($configuration->isDryRun()) {
                $message = \sprintf('File "%s" will be moved to "%s"', $movedFile->getFilePath(), $movedFile->getNewFilePath());
                $this->rectorOutputStyle->note($message);
            } else {
                $this->filesystem->dumpFile($movedFile->getNewFilePath(), $fileContent);
                $this->filesystem->remove($movedFile->getFilePath());
                $message = \sprintf('File "%s" was moved to "%s"', $movedFile->getFilePath(), $movedFile->getNewFilePath());
                $this->rectorOutputStyle->note($message);
            }
        }
    }
}
