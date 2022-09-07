<?php

declare (strict_types=1);
namespace Rector\Core\PhpParser\Comparing;

use PhpParser\Node;
use Rector\Comments\CommentRemover;
use Rector\Core\Contract\PhpParser\NodePrinterInterface;
final class NodeComparator
{
    public function __construct(
        /**
         * @readonly
         */
        private CommentRemover $commentRemover,
        /**
         * @readonly
         */
        private NodePrinterInterface $nodePrinter
    )
    {
    }
    /**
     * Removes all comments from both nodes
     * @param \PhpParser\Node|mixed[]|null $node
     */
    public function printWithoutComments($node) : string
    {
        $node = $this->commentRemover->removeFromNode($node);
        $content = $this->nodePrinter->print($node);
        return \trim($content);
    }
    /**
     * @param \PhpParser\Node|mixed[]|null $firstNode
     * @param \PhpParser\Node|mixed[]|null $secondNode
     */
    public function areNodesEqual($firstNode, $secondNode) : bool
    {
        return $this->printWithoutComments($firstNode) === $this->printWithoutComments($secondNode);
    }
    /**
     * @api
     * @param Node[] $availableNodes
     */
    public function isNodeEqual(Node $singleNode, array $availableNodes) : bool
    {
        foreach ($availableNodes as $availableNode) {
            if ($this->areNodesEqual($singleNode, $availableNode)) {
                return \true;
            }
        }
        return \false;
    }
    /**
     * Checks even clone nodes
     */
    public function areSameNode(Node $firstNode, Node $secondNode) : bool
    {
        if ($firstNode === $secondNode) {
            return \true;
        }
        if ($firstNode->getStartTokenPos() !== $secondNode->getStartTokenPos()) {
            return \false;
        }
        if ($firstNode->getEndTokenPos() !== $secondNode->getEndTokenPos()) {
            return \false;
        }
        $firstClass = $firstNode::class;
        $secondClass = $secondNode::class;
        return $firstClass === $secondClass;
    }
}
