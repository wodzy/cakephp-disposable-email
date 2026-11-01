<?php
declare(strict_types=1);

namespace CakeDisposableEmail;

use Wodzy\DisposableEmail\BlocklistInterface;

/**
 * Holds the blocklist shared by the validation rules and the DI container.
 *
 * CakePHP validation providers are static by design (`Validator::addDefaultProvider()`),
 * so the `notDisposable` rule needs a static access point; this is it. Everywhere
 * else, ask the container for `Wodzy\DisposableEmail\BlocklistInterface` instead.
 *
 * The blocklist is built on first use and kept for the lifetime of the PHP
 * process. With PHP-FPM that is one request. Long-running workers (queue
 * consumers, RoadRunner, Swoole...) keep it until they restart, so restart them
 * after `bin/cake disposable_email update`, or call `reset()`.
 */
final class DisposableEmailRegistry
{
    private static ?BlocklistInterface $instance = null;

    /**
     * Returns the shared blocklist, building it from configuration on first use.
     *
     * @return \Wodzy\DisposableEmail\BlocklistInterface
     */
    public static function get(): BlocklistInterface
    {
        return self::$instance ??= (new BlocklistFactory())->create(DisposableEmailConfig::fromConfigure());
    }

    /**
     * Replaces the shared blocklist, e.g. with a fixed list in tests.
     *
     * @param \Wodzy\DisposableEmail\BlocklistInterface $blocklist Blocklist to use.
     * @return void
     */
    public static function set(BlocklistInterface $blocklist): void
    {
        self::$instance = $blocklist;
    }

    /**
     * Forgets the shared blocklist so the next `get()` reads the configuration again.
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$instance = null;
    }
}
