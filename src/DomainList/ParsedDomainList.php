<?php
declare(strict_types=1);

namespace CakeDisposableEmail\DomainList;

/**
 * Outcome of parsing a domain list.
 */
final class ParsedDomainList
{
    /**
     * @param list<string> $domains Valid, unique, sorted domains.
     * @param list<string> $skipped Protected providers found in the list and left out.
     * @param int $invalidLines Lines that were not valid domain names.
     */
    public function __construct(
        public readonly array $domains,
        public readonly array $skipped,
        public readonly int $invalidLines,
    ) {
    }
}
