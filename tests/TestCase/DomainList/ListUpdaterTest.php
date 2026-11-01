<?php
declare(strict_types=1);

namespace CakeDisposableEmail\Test\TestCase\DomainList;

use Cake\Http\Client;
use Cake\Http\Client\Exception\NetworkException;
use Cake\Http\Client\Request;
use Cake\Http\Client\Response;
use Cake\TestSuite\TestCase;
use CakeDisposableEmail\DisposableEmailConfig;
use CakeDisposableEmail\DomainList\ListUpdater;
use RuntimeException;
use Wodzy\DisposableEmail\DomainSet;

class ListUpdaterTest extends TestCase
{
    private const URL = 'https://lists.test/disposable.conf';

    private string $dir;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = TMP . 'updater-' . bin2hex(random_bytes(4));
        $this->path = $this->dir . DS . 'domains.json';
    }

    protected function tearDown(): void
    {
        Client::clearMockResponses();
        if (is_dir($this->dir)) {
            chmod($this->dir, 0775);
            array_map('unlink', glob($this->dir . DS . '*') ?: []);
            rmdir($this->dir);
        }
        parent::tearDown();
    }

    public function testUpdateWritesADisposableEmailCompatibleFile(): void
    {
        $this->mockBody("b.test\na.test\ngmail.com\nnot a domain\n");

        $result = $this->updater(minimumDomains: 2)->update();

        $this->assertSame($this->path, $result->path);
        $this->assertSame(2, $result->count);
        $this->assertSame(['gmail.com'], $result->skipped);
        $this->assertSame(1, $result->invalidLines);
        $this->assertSame(['a.test', 'b.test'], DomainSet::fromFile($this->path)->toArray());

        $json = json_decode((string)file_get_contents($this->path), true);
        $this->assertIsArray($json);
        $this->assertSame(self::URL, $json['source']);
        $this->assertArrayHasKey('updatedAt', $json);
    }

    public function testUpdateRefusesASuspiciouslySmallList(): void
    {
        $this->mockBody("a.test\n");

        try {
            $this->updater(minimumDomains: 2)->update();
            $this->fail('Expected exception was not thrown.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Only 1 domains', $e->getMessage());
        }
        $this->assertFileDoesNotExist($this->path);
    }

    public function testUpdateFailsOnHttpError(): void
    {
        Client::addMockResponse('GET', self::URL, new Response(['HTTP/1.1 500 Internal Server Error'], ''));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP status 500');

        $this->updater(minimumDomains: 1)->update();
    }

    public function testUpdateFailsOnNetworkError(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('get')->willThrowException(new NetworkException('Connection refused', new Request(self::URL)));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Connection refused');

        $this->updater(minimumDomains: 1, client: $client)->update();
    }

    public function testUpdateRefusesAnOversizedBody(): void
    {
        $this->mockBody(str_repeat('a', ListUpdater::MAX_BYTES + 1));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('larger than');

        $this->updater(minimumDomains: 1)->update();
    }

    public function testUpdateKeepsTheExistingFileOnFailure(): void
    {
        mkdir($this->dir, 0775, true);
        file_put_contents($this->path, '{"domains": ["kept.test"]}');
        $this->mockBody('');

        try {
            $this->updater(minimumDomains: 1)->update();
            $this->fail('Expected exception was not thrown.');
        } catch (RuntimeException) {
        }

        $this->assertSame(['kept.test'], DomainSet::fromFile($this->path)->toArray());
    }

    public function testUpdateFailsWhenTheDirectoryIsNotWritable(): void
    {
        if (function_exists('posix_getuid') && posix_getuid() === 0) {
            $this->markTestSkipped('root can write to read-only directories.');
        }
        mkdir($this->dir, 0555, true);
        $this->mockBody("a.test\n");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('is not writable');

        $this->updater(minimumDomains: 1)->update();
    }

    public function testUpdateNeedsATargetPath(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('downloadedListPath');

        (new ListUpdater(DisposableEmailConfig::fromArray(['downloadedListPath' => null])))->update();
    }

    private function mockBody(string $body): void
    {
        Client::addMockResponse('GET', self::URL, new Response(['HTTP/1.1 200 OK'], $body));
    }

    private function updater(int $minimumDomains, ?Client $client = null): ListUpdater
    {
        return new ListUpdater(DisposableEmailConfig::fromArray([
            'downloadedListPath' => $this->path,
            'updateUrl' => self::URL,
            'minimumDomains' => $minimumDomains,
        ]), $client);
    }
}
