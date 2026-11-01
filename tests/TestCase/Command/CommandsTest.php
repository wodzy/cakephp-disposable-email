<?php
declare(strict_types=1);

namespace CakeDisposableEmail\Test\TestCase\Command;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Core\Configure;
use Cake\Http\Client;
use Cake\Http\Client\Response;
use Cake\TestSuite\TestCase;
use CakeDisposableEmail\Command\CheckCommand;
use CakeDisposableEmail\DisposableEmailRegistry;
use TestApp\Application;

/**
 * Runs the commands through CakePHP's command runner, so the constructor
 * arguments are resolved by the plugin's DI container definitions.
 */
class CommandsTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    private const URL = 'https://lists.test/list.conf';

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configApplication(Application::class, [CONFIG]);
        $this->path = TMP . 'command-' . bin2hex(random_bytes(4)) . '.json';
        Configure::write('DisposableEmail', [
            'domains' => ['spam.test'],
            'neverBlock' => ['partner.yopmail.com'],
            'downloadedListPath' => $this->path,
            'updateUrl' => self::URL,
            'minimumDomains' => 2,
            'cacheConfig' => null,
        ]);
        DisposableEmailRegistry::reset();
    }

    protected function tearDown(): void
    {
        Client::clearMockResponses();
        DisposableEmailRegistry::reset();
        if (is_file($this->path)) {
            unlink($this->path);
        }
        parent::tearDown();
    }

    public function testCheckBlockedEmail(): void
    {
        $this->exec('disposable_email check john@spam.test');

        $this->assertExitError();
        $this->assertErrorContains('is blocked');
    }

    public function testCheckAllowedEmail(): void
    {
        $this->exec('disposable_email check john@partner.yopmail.com');

        $this->assertExitSuccess();
        $this->assertOutputContains('is allowed');
    }

    public function testCheckInvalidEmail(): void
    {
        $this->exec('disposable_email check nope');

        $this->assertExitCode(CheckCommand::CODE_INVALID);
        $this->assertErrorContains('not a valid email address');
    }

    public function testUpdateDownloadsAndTheNewListIsUsed(): void
    {
        Client::addMockResponse('GET', self::URL, new Response(['HTTP/1.1 200 OK'], "one.test\ntwo.test\ngmail.com\n"));

        $this->exec('disposable_email update');

        $this->assertExitSuccess();
        $this->assertOutputContains('2 domains written');
        $this->assertOutputContains('Skipped protected providers: gmail.com');
        $this->assertFileExists($this->path);

        DisposableEmailRegistry::reset();
        $this->exec('disposable_email check john@two.test');
        $this->assertExitError();
    }

    public function testUpdateWithUrlOption(): void
    {
        Client::addMockResponse('GET', 'https://other.test/list.conf', new Response(['HTTP/1.1 200 OK'], "one.test\ntwo.test\n"));

        $this->exec('disposable_email update --url https://other.test/list.conf');

        $this->assertExitSuccess();
        $this->assertOutputContains('https://other.test/list.conf');
    }

    public function testUpdateFailure(): void
    {
        Client::addMockResponse('GET', self::URL, new Response(['HTTP/1.1 200 OK'], "one.test\n"));

        $this->exec('disposable_email update');

        $this->assertExitError();
        $this->assertErrorContains('existing list was kept');
    }
}
