<?php
declare(strict_types=1);

namespace CakeDisposableEmail\Test\TestCase;

use Cake\Cache\Cache;
use Cake\TestSuite\TestCase;
use CakeDisposableEmail\BlocklistFactory;
use CakeDisposableEmail\DisposableEmailConfig;
use Wodzy\DisposableEmail\Blocklist;
use Wodzy\DisposableEmail\Exception\DomainListFileNotFoundException;

class BlocklistFactoryTest extends TestCase
{
    private string $listPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->listPath = TMP . 'factory-' . bin2hex(random_bytes(4)) . '.json';
        Cache::clear('default');
    }

    protected function tearDown(): void
    {
        if (is_file($this->listPath)) {
            unlink($this->listPath);
        }
        Cache::clear('default');
        parent::tearDown();
    }

    public function testBundledListIsUsedByDefault(): void
    {
        $blocklist = $this->create([]);

        $this->assertTrue($blocklist->blocks('john@yopmail.com'));
        $this->assertFalse($blocklist->blocks('john@gmail.com'));
    }

    public function testBundledListCanBeDisabled(): void
    {
        $blocklist = $this->create(['useBundledList' => false]);

        $this->assertCount(0, $blocklist);
        $this->assertFalse($blocklist->blocks('john@yopmail.com'));
    }

    public function testInlineDomainsAreBlockedWithTheirSubdomains(): void
    {
        $blocklist = $this->create(['useBundledList' => false, 'domains' => ['Spam.TEST']]);

        $this->assertTrue($blocklist->blocks('a@spam.test'));
        $this->assertTrue($blocklist->blocks('a@sub.spam.test'));
    }

    public function testNeverBlockWinsEvenForSubdomainsOfABlockedDomain(): void
    {
        $blocklist = $this->create([
            'domains' => ['spam.test'],
            'neverBlock' => ['partner.yopmail.com', 'spam.test'],
        ]);

        $this->assertTrue($blocklist->blocks('a@yopmail.com'));
        $this->assertFalse($blocklist->blocks('a@partner.yopmail.com'));
        $this->assertFalse($blocklist->blocks('a@eu.partner.yopmail.com'));
        $this->assertFalse($blocklist->blocks('a@spam.test'));
        $this->assertTrue($blocklist->blocks('a@mailinator.com'));
    }

    public function testExtraFilesAreLoaded(): void
    {
        $blocklist = $this->create([
            'useBundledList' => false,
            'files' => [TESTS . 'Fixture' . DS . 'extra_domains.json'],
        ]);

        $this->assertTrue($blocklist->blocks('a@spam-from-file.test'));
    }

    public function testMissingExtraFileThrows(): void
    {
        $this->expectException(DomainListFileNotFoundException::class);

        $this->create(['files' => ['/nope/missing.json']]);
    }

    public function testDownloadedListIsLoadedOnlyWhenItExists(): void
    {
        $this->assertCount(0, $this->create(['useBundledList' => false]));

        $this->writeList(['downloaded.test']);
        $this->assertTrue($this->create(['useBundledList' => false])->blocks('a@downloaded.test'));
    }

    public function testCachedListIsReusedWhileTheSourcesAreUnchanged(): void
    {
        $this->writeList(['first.test']);
        $mtime = (int)filemtime($this->listPath);
        $this->assertTrue($this->create(['useBundledList' => false])->blocks('a@first.test'));

        // Same size and modification time: the source looks unchanged, so the cache is used.
        $this->writeList(['other.test']);
        touch($this->listPath, $mtime);
        $this->assertTrue($this->create(['useBundledList' => false])->blocks('a@first.test'));
    }

    public function testCacheIsInvalidatedWhenASourceChanges(): void
    {
        $this->writeList(['first.test']);
        $this->assertTrue($this->create(['useBundledList' => false])->blocks('a@first.test'));

        // What `disposable_email update` does: a new file, with a new modification time.
        $this->writeList(['second.test']);
        touch($this->listPath, time() + 10);
        $blocklist = $this->create(['useBundledList' => false]);

        $this->assertTrue($blocklist->blocks('a@second.test'));
        $this->assertFalse($blocklist->blocks('a@first.test'));
    }

    public function testCacheIsInvalidatedWhenInlineDomainsChange(): void
    {
        $this->assertTrue($this->create(['useBundledList' => false, 'domains' => ['a.test']])->blocks('x@a.test'));
        $this->assertTrue($this->create(['useBundledList' => false, 'domains' => ['b.test']])->blocks('x@b.test'));
    }

    public function testCachingCanBeDisabled(): void
    {
        $this->writeList(['first.test']);
        $mtime = (int)filemtime($this->listPath);
        $this->create(['useBundledList' => false, 'cacheConfig' => null]);

        $this->writeList(['other.test']);
        touch($this->listPath, $mtime);

        $this->assertTrue($this->create(['useBundledList' => false, 'cacheConfig' => null])->blocks('a@other.test'));
    }

    /**
     * @param array<string, mixed> $options
     */
    private function create(array $options): Blocklist
    {
        $options += ['downloadedListPath' => $this->listPath];

        return (new BlocklistFactory())->create(DisposableEmailConfig::fromArray($options));
    }

    /**
     * @param list<string> $domains
     */
    private function writeList(array $domains): void
    {
        file_put_contents($this->listPath, json_encode(['domains' => $domains]));
    }
}
