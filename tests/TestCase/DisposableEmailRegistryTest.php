<?php
declare(strict_types=1);

namespace CakeDisposableEmail\Test\TestCase;

use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use CakeDisposableEmail\DisposableEmailRegistry;
use Wodzy\DisposableEmail\Blocklist;

class DisposableEmailRegistryTest extends TestCase
{
    protected function tearDown(): void
    {
        DisposableEmailRegistry::reset();
        parent::tearDown();
    }

    public function testGetBuildsFromConfigureAndIsMemoizedUntilReset(): void
    {
        Configure::write('DisposableEmail', ['useBundledList' => false, 'domains' => ['first.test'], 'cacheConfig' => null]);
        $first = DisposableEmailRegistry::get();
        $this->assertTrue($first->blocks('a@first.test'));

        Configure::write('DisposableEmail', ['useBundledList' => false, 'domains' => ['second.test'], 'cacheConfig' => null]);
        $this->assertSame($first, DisposableEmailRegistry::get());

        DisposableEmailRegistry::reset();
        $this->assertTrue(DisposableEmailRegistry::get()->blocks('a@second.test'));
    }

    public function testSetInjectsACustomBlocklist(): void
    {
        $custom = Blocklist::fromDomains(['custom.test']);
        DisposableEmailRegistry::set($custom);

        $this->assertSame($custom, DisposableEmailRegistry::get());
    }
}
