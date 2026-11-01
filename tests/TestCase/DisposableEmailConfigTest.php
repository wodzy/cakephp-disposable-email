<?php
declare(strict_types=1);

namespace CakeDisposableEmail\Test\TestCase;

use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use CakeDisposableEmail\DisposableEmailConfig;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;

class DisposableEmailConfigTest extends TestCase
{
    public function testDefaultsComeFromASingleSource(): void
    {
        Configure::delete('DisposableEmail');

        $config = DisposableEmailConfig::fromConfigure();

        $this->assertSame(DisposableEmailConfig::defaults(), $config->toArray());
        $this->assertSame(DisposableEmailConfig::defaults(), DisposableEmailConfig::fromArray([])->toArray());
        $this->assertTrue($config->useBundledList);
        $this->assertSame([], $config->neverBlock);
        $this->assertSame(CONFIG . 'disposable_email_domains.json', $config->downloadedListPath);
        $this->assertSame(1000, $config->minimumDomains);
        $this->assertSame('default', $config->cacheConfig);
    }

    public function testReadsConfigure(): void
    {
        Configure::write('DisposableEmail', [
            'useBundledList' => false,
            'domains' => ['a.test'],
            'neverBlock' => ['b.test'],
            'downloadedListPath' => null,
            'minimumDomains' => 5,
            'cacheConfig' => null,
        ]);

        $config = DisposableEmailConfig::fromConfigure();

        $this->assertFalse($config->useBundledList);
        $this->assertSame(['a.test'], $config->domains);
        $this->assertSame(['b.test'], $config->neverBlock);
        $this->assertNull($config->downloadedListPath);
        $this->assertSame(5, $config->minimumDomains);
        $this->assertNull($config->cacheConfig);
    }

    public function testWithUpdateUrlKeepsEveryOtherOption(): void
    {
        $config = DisposableEmailConfig::fromArray(['domains' => ['a.test'], 'minimumDomains' => 3]);

        $changed = $config->withUpdateUrl('https://other.test/list.conf');

        $this->assertSame('https://other.test/list.conf', $changed->updateUrl);
        $this->assertSame(
            array_replace($config->toArray(), ['updateUrl' => 'https://other.test/list.conf']),
            $changed->toArray(),
        );
        $this->assertSame(DisposableEmailConfig::DEFAULT_UPDATE_URL, $config->updateUrl, 'the original is unchanged');
    }

    public function testUnknownOptionThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown "DisposableEmail" option(s): allow.');

        DisposableEmailConfig::fromArray(['allow' => ['a.test']]);
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('invalidConfigProvider')]
    public function testInvalidOptionsThrow(array $config, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        DisposableEmailConfig::fromArray($config);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidConfigProvider(): iterable
    {
        yield 'domains not a list' => [['domains' => 'a.test'], '"DisposableEmail.domains" must be a list of strings.'];
        yield 'domains with a non string' => [['domains' => ['a.test', 42]], 'DisposableEmail.domains'];
        yield 'neverBlock not a list' => [['neverBlock' => true], 'DisposableEmail.neverBlock'];
        yield 'files with a non string' => [['files' => [null]], 'DisposableEmail.files'];
        yield 'useBundledList not a bool' => [['useBundledList' => 'yes'], 'must be a boolean'];
        yield 'downloadedListPath not a string' => [['downloadedListPath' => 12], 'DisposableEmail.downloadedListPath'];
        yield 'empty downloadedListPath' => [['downloadedListPath' => ''], 'DisposableEmail.downloadedListPath'];
        yield 'empty updateUrl' => [['updateUrl' => ''], 'DisposableEmail.updateUrl'];
        yield 'zero minimumDomains' => [['minimumDomains' => 0], 'must be an integer greater than 0'];
        yield 'cacheConfig not a string' => [['cacheConfig' => false], 'DisposableEmail.cacheConfig'];
    }

    public function testNonArrayConfigureKeyThrows(): void
    {
        Configure::write('DisposableEmail', 'nope');

        $this->expectException(InvalidArgumentException::class);

        DisposableEmailConfig::fromConfigure();
    }
}
