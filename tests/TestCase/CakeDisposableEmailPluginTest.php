<?php
declare(strict_types=1);

namespace CakeDisposableEmail\Test\TestCase;

use Cake\Core\Configure;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use CakeDisposableEmail\Command\CheckCommand;
use CakeDisposableEmail\Command\UpdateCommand;
use CakeDisposableEmail\DisposableEmailConfig;
use CakeDisposableEmail\DisposableEmailRegistry;
use TestApp\Application;
use Wodzy\DisposableEmail\BlocklistInterface;

class CakeDisposableEmailPluginTest extends TestCase
{
    use IntegrationTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        Configure::write('DisposableEmail', ['domains' => ['spam.test'], 'cacheConfig' => null]);
        DisposableEmailRegistry::reset();
    }

    protected function tearDown(): void
    {
        DisposableEmailRegistry::reset();
        parent::tearDown();
    }

    public function testContainerSharesTheBlocklistUsedByTheValidationRules(): void
    {
        $app = new Application(CONFIG);
        $app->bootstrap();
        $app->pluginBootstrap();
        $container = $app->getContainer();

        $blocklist = $container->get(BlocklistInterface::class);

        $this->assertSame(DisposableEmailRegistry::get(), $blocklist);
        $this->assertSame($blocklist, $container->get(BlocklistInterface::class));
        $this->assertInstanceOf(DisposableEmailConfig::class, $container->get(DisposableEmailConfig::class));
        $this->assertInstanceOf(CheckCommand::class, $container->get(CheckCommand::class));
        $this->assertInstanceOf(UpdateCommand::class, $container->get(UpdateCommand::class));
    }

    public function testBlocklistIsInjectedIntoControllerActions(): void
    {
        $this->get('/signups/check?email=jane@spam.test');
        $this->assertResponseOk();
        $this->assertResponseEquals('blocked');

        $this->get('/signups/check?email=jane@gmail.com');
        $this->assertResponseEquals('allowed');
    }
}
