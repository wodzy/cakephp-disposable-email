<?php
declare(strict_types=1);

namespace CakeDisposableEmail;

use Cake\Core\Configure;
use InvalidArgumentException;

/**
 * Typed, validated view of the `DisposableEmail` configuration key.
 *
 * `defaults()` is the single source of default values. See
 * `config/disposable_email.example.php` for what every option does.
 */
final class DisposableEmailConfig
{
    /**
     * Community-maintained list used by `bin/cake disposable_email update`.
     */
    public const DEFAULT_UPDATE_URL = 'https://raw.githubusercontent.com/disposable-email-domains/'
        . 'disposable-email-domains/main/disposable_email_blocklist.conf';

    /**
     * Name of the downloaded list file, inside the application's `config/` directory.
     */
    public const DOWNLOADED_LIST_FILE = 'disposable_email_domains.json';

    /**
     * @param bool $useBundledList Use the list shipped with wodzy/disposable-email.
     * @param list<string> $domains Extra domains to block.
     * @param list<string> $neverBlock Domains (and their subdomains) that are never blocked.
     * @param list<string> $files Extra JSON files (`{"domains": [...]}`) to load.
     * @param string|null $downloadedListPath File written by `disposable_email update` (null disables it).
     * @param string $updateUrl Source of `disposable_email update` (one domain per line, `#` comments).
     * @param int $minimumDomains `disposable_email update` refuses a download with fewer domains.
     * @param string|null $cacheConfig Cache configuration storing the merged list (null disables caching).
     */
    private function __construct(
        public readonly bool $useBundledList,
        public readonly array $domains,
        public readonly array $neverBlock,
        public readonly array $files,
        public readonly ?string $downloadedListPath,
        public readonly string $updateUrl,
        public readonly int $minimumDomains,
        public readonly ?string $cacheConfig,
    ) {
    }

    /**
     * Default value of every option.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'useBundledList' => true,
            'domains' => [],
            'neverBlock' => [],
            'files' => [],
            'downloadedListPath' => defined('CONFIG') ? CONFIG . self::DOWNLOADED_LIST_FILE : null,
            'updateUrl' => self::DEFAULT_UPDATE_URL,
            'minimumDomains' => 1000,
            'cacheConfig' => 'default',
        ];
    }

    /**
     * Reads the `DisposableEmail` key from Configure.
     *
     * @return self
     * @throws \InvalidArgumentException When an option is unknown or has the wrong type.
     */
    public static function fromConfigure(): self
    {
        $config = Configure::read('DisposableEmail', []);
        if (!is_array($config)) {
            throw new InvalidArgumentException('The "DisposableEmail" configuration must be an array.');
        }

        return self::fromArray($config);
    }

    /**
     * Builds the configuration from an array; missing options take their default value.
     *
     * @param array<mixed> $config Options to override.
     * @return self
     * @throws \InvalidArgumentException When an option is unknown or has the wrong type.
     */
    public static function fromArray(array $config): self
    {
        $defaults = self::defaults();
        $unknown = array_diff(array_keys($config), array_keys($defaults));
        if ($unknown !== []) {
            throw new InvalidArgumentException(sprintf(
                'Unknown "DisposableEmail" option(s): %s. Valid options are: %s.',
                implode(', ', $unknown),
                implode(', ', array_keys($defaults)),
            ));
        }
        $config += $defaults;

        return new self(
            useBundledList: self::bool($config, 'useBundledList'),
            domains: self::stringList($config, 'domains'),
            neverBlock: self::stringList($config, 'neverBlock'),
            files: self::stringList($config, 'files'),
            downloadedListPath: self::nullableString($config, 'downloadedListPath'),
            updateUrl: self::nonEmptyString($config, 'updateUrl'),
            minimumDomains: self::positiveInt($config, 'minimumDomains'),
            cacheConfig: self::nullableString($config, 'cacheConfig'),
        );
    }

    /**
     * Same configuration, downloading from another URL.
     *
     * @param string $url Source of the list.
     * @return self
     */
    public function withUpdateUrl(string $url): self
    {
        return self::fromArray(['updateUrl' => $url] + $this->toArray());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'useBundledList' => $this->useBundledList,
            'domains' => $this->domains,
            'neverBlock' => $this->neverBlock,
            'files' => $this->files,
            'downloadedListPath' => $this->downloadedListPath,
            'updateUrl' => $this->updateUrl,
            'minimumDomains' => $this->minimumDomains,
            'cacheConfig' => $this->cacheConfig,
        ];
    }

    /**
     * @param array<mixed> $config Options.
     * @param string $key Option name.
     * @return bool
     */
    private static function bool(array $config, string $key): bool
    {
        if (!is_bool($config[$key])) {
            throw self::invalid($key, 'a boolean');
        }

        return $config[$key];
    }

    /**
     * @param array<mixed> $config Options.
     * @param string $key Option name.
     * @return string|null
     */
    private static function nullableString(array $config, string $key): ?string
    {
        $value = $config[$key];
        if ($value !== null && (!is_string($value) || $value === '')) {
            throw self::invalid($key, 'a non-empty string or null');
        }

        return $value;
    }

    /**
     * @param array<mixed> $config Options.
     * @param string $key Option name.
     * @return string
     */
    private static function nonEmptyString(array $config, string $key): string
    {
        $value = $config[$key];
        if (!is_string($value) || $value === '') {
            throw self::invalid($key, 'a non-empty string');
        }

        return $value;
    }

    /**
     * @param array<mixed> $config Options.
     * @param string $key Option name.
     * @return int
     */
    private static function positiveInt(array $config, string $key): int
    {
        $value = $config[$key];
        if (!is_int($value) || $value < 1) {
            throw self::invalid($key, 'an integer greater than 0');
        }

        return $value;
    }

    /**
     * @param array<mixed> $config Options.
     * @param string $key Option name.
     * @return list<string>
     */
    private static function stringList(array $config, string $key): array
    {
        $value = $config[$key];
        if (!is_array($value)) {
            throw self::invalid($key, 'a list of strings');
        }

        $list = [];
        foreach ($value as $item) {
            if (!is_string($item)) {
                throw self::invalid($key, 'a list of strings');
            }
            $list[] = $item;
        }

        return $list;
    }

    /**
     * @param string $key Option name.
     * @param string $expected Expected type.
     * @return \InvalidArgumentException
     */
    private static function invalid(string $key, string $expected): InvalidArgumentException
    {
        return new InvalidArgumentException(sprintf('"DisposableEmail.%s" must be %s.', $key, $expected));
    }
}
