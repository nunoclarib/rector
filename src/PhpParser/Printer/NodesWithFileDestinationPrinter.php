<?php

declare (strict_types=1);
namespace Rector\Core\PhpParser\Printer;

use Rector\Core\Contract\PhpParser\NodePrinterInterface;
use Rector\FileSystemRector\Contract\FileWithNodesInterface;
use Rector\PostRector\Application\PostFileProcessor;
final class NodesWithFileDestinationPrinter
{
    public function __construct(
        /**
         * @readonly
         */
        private readonly NodePrinterInterface $nodePrinter,
        /**
         * @readonly
         */
        private readonly PostFileProcessor $postFileProcessor
    )
    {
    }
    public function printNodesWithFileDestination(FileWithNodesInterface $fileWithNodes) : string
    {
        $nodes = $this->postFileProcessor->traverse($fileWithNodes->getNodes());
        return $this->nodePrinter->prettyPrintFile($nodes);
    }
}
