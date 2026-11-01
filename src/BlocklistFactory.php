<?php
declare(strict_types=1);

namespace CakeDisposableEmail;

use Cake\Cache\Cache;
use Wodzy\DisposableEmail\Blocklist;
use Wodzy\DisposableEmail\DomainSet;

/**
 * Builds the blocklist described by the configuration.
 *
 * Merging and normalizing ~10,000 domains takes a few milliseconds, so the
 * merged list is stored with `Cache::remember()`. The cache key changes when a
 * source file is modified (for example by `disposable_email update`) or when the
 * configured domains change, so a stale list is never served.
 */
final class BlocklistFactory
{
    /**
     * Builds the blocklist.
     *
     * @param \CakeDisposableEmail\DisposableEmailConfig $config Configuration.
     * @return \Wodzy\DisposableEmail\Blocklist
     * @throws \Wodzy\DisposableEmail\Exception\DisposableEmailException When a configured file is missing or invalid.
     */
    public function create(DisposableEmailConfig $config): Blocklist
    {
        return new Blocklist($this->blockedDomains($config), new DomainSet($config->neverBlock));
    }

    /**
     * @param \CakeDisposableEmail\DisposableEmailConfig $config Configuration.
     * @return \Wodzy\DisposableEmail\DomainSet
     */
    private function blockedDomains(DisposableEmailConfig $config): DomainSet
    {
        if ($config->cacheConfig === null) {
            return $this->mergeSources($config);
        }

        $cached = Cache::remember(
            'disposable_email_' . $this->fingerprint($config),
            fn(): array => $this->mergeSources($config)->toArray(),
            $config->cacheConfig,
        );

        if (!is_array($cached) || !array_is_list($cached)) {
            return $this->mergeSources($config);
        }

        return DomainSet::fromNormalized(array_values(array_filter($cached, is_string(...))));
    }

    /**
     * Sources, in order: bundled list, downloaded list (when the file exists), extra files, inline domains.
     *
     * @param \CakeDisposableEmail\DisposableEmailConfig $config Configuration.
     * @return \Wodzy\DisposableEmail\DomainSet
     */
    private function mergeSources(DisposableEmailConfig $config): DomainSet
    {
        $sets = [];
        if ($config->useBundledList) {
            $sets[] = DomainSet::bundled();
        }
        if ($config->downloadedListPath !== null && is_file($config->downloadedListPath)) {
            $sets[] = DomainSet::fromFile($config->downloadedListPath);
        }
        foreach ($config->files as $file) {
            $sets[] = DomainSet::fromFile($file);
        }

        return (new DomainSet($config->domains))->merge(...$sets);
    }

    /**
     * Identifies the content of every source, so that any change produces a new cache key.
     *
     * @param \CakeDisposableEmail\DisposableEmailConfig $config Configuration.
     * @return string
     */
    private function fingerprint(DisposableEmailConfig $config): string
    {
        $files = $config->files;
        if ($config->useBundledList) {
            $files[] = DomainSet::bundledListPath();
        }
        if ($config->downloadedListPath !== null) {
            $files[] = $config->downloadedListPath;
        }

        clearstatcache();
        $versions = [];
        foreach ($files as $file) {
            $versions[$file] = is_file($file) ? [filemtime($file), filesize($file)] : null;
        }

        return sha1(serialize([$versions, $config->domains]));
    }
}
