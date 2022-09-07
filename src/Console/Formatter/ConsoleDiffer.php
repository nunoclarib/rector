<?php

declare (strict_types=1);
namespace Rector\Core\Console\Formatter;

use RectorPrefix202209\SebastianBergmann\Diff\Differ;
final class ConsoleDiffer
{
    public function __construct(
        /**
         * @readonly
         */
        private Differ $differ,
        /**
         * @readonly
         */
        private \Rector\Core\Console\Formatter\ColorConsoleDiffFormatter $colorConsoleDiffFormatter
    )
    {
    }
    public function diff(string $old, string $new) : string
    {
        $diff = $this->differ->diff($old, $new);
        return $this->colorConsoleDiffFormatter->format($diff);
    }
}
