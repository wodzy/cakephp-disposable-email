<?php
declare(strict_types=1);

namespace CakeDisposableEmail\DomainList;

use Cake\Http\Client;
use CakeDisposableEmail\DisposableEmailConfig;
use RuntimeException;

/**
 * Downloads a disposable-domain list and stores it as a DisposableEmail JSON file.
 *
 * Safety nets, because a bad list would block real users at signup:
 * - the download must succeed (HTTP 200), stay under `MAX_BYTES`, and contain
 *   at least `minimumDomains` entries,
 * - mainstream providers are always left out (see `DomainListParser::PROTECTED_DOMAINS`),
 * - the file is written atomically, so a failure never leaves a half-written list.
 */
final class ListUpdater
{
    /**
     * Largest accepted response body (the community list is about 250 KB).
     */
    public const MAX_BYTES = 20 * 1024 * 1024;

    private readonly Client $client;

    private readonly DomainListParser $parser;

    /**
     * @param \CakeDisposableEmail\DisposableEmailConfig $config Configuration.
     * @param \Cake\Http\Client|null $client HTTP client.
     * @param \CakeDisposableEmail\DomainList\DomainListParser|null $parser Parser.
     */
    public function __construct(
        private readonly DisposableEmailConfig $config,
        ?Client $client = null,
        ?DomainListParser $parser = null,
    ) {
        $this->client = $client ?? new Client(['timeout' => 30, 'redirect' => 2]);
        $this->parser = $parser ?? new DomainListParser();
    }

    /**
     * Downloads, filters and writes the list.
     *
     * @return \CakeDisposableEmail\DomainList\UpdateResult
     * @throws \RuntimeException When the download fails, looks wrong, or cannot be written.
     */
    public function update(): UpdateResult
    {
        $path = $this->config->downloadedListPath;
        if ($path === null) {
            throw new RuntimeException('"DisposableEmail.downloadedListPath" is null, nothing to update.');
        }

        $parsed = $this->parser->parse($this->download());
        if (count($parsed->domains) < $this->config->minimumDomains) {
            throw new RuntimeException(sprintf(
                'Only %d domains were found in "%s" (minimum: %d). The existing list was kept.',
                count($parsed->domains),
                $this->config->updateUrl,
                $this->config->minimumDomains,
            ));
        }

        $this->write($path, $parsed->domains);

        return new UpdateResult($path, count($parsed->domains), $parsed->skipped, $parsed->invalidLines);
    }

    /**
     * @return string
     */
    private function download(): string
    {
        $url = $this->config->updateUrl;
        $response = $this->client->get($url);
        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException(sprintf(
                'Download of "%s" failed with HTTP status %d.',
                $url,
                $response->getStatusCode(),
            ));
        }

        $body = $response->getStringBody();
        if (strlen($body) > self::MAX_BYTES) {
            throw new RuntimeException(sprintf(
                'The list downloaded from "%s" is larger than %d bytes. The existing list was kept.',
                $url,
                self::MAX_BYTES,
            ));
        }

        return $body;
    }

    /**
     * @param string $path Target file.
     * @param list<string> $domains Domains.
     * @return void
     */
    private function write(string $path, array $domains): void
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException(sprintf('Cannot create directory "%s".', $dir));
        }
        if (!is_writable($dir)) {
            throw new RuntimeException(sprintf('Directory "%s" is not writable.', $dir));
        }

        $json = json_encode([
            'source' => $this->config->updateUrl,
            'updatedAt' => gmdate('c'),
            'domains' => $domains,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (file_put_contents($tmp, $json . "\n") === false || !rename($tmp, $path)) {
            if (is_file($tmp)) {
                unlink($tmp);
            }
            throw new RuntimeException(sprintf('Cannot write "%s".', $path));
        }
    }
}
