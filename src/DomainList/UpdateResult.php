<?php
declare(strict_types=1);

namespace CakeDisposableEmail\DomainList;

/**
 * Outcome of a list update.
 */
final class UpdateResult
{
    /**
     * @param string $path File that was written.
     * @param int $count Number of domains written.
     * @param list<string> $skipped Protected providers found in the source and left out.
     * @param int $invalidLines Lines that were not valid domain names.
     */
    public function __construct(
        public readonly string $path,
        public readonly int $count,
        public readonly array $skipped,
        public readonly int $invalidLines,
    ) {
    }
}
