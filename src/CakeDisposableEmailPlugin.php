<?php
declare(strict_types=1);

namespace CakeDisposableEmail;

use Cake\Console\CommandCollection;
use Cake\Core\BasePlugin;
use Cake\Core\ContainerInterface;
use Cake\Core\PluginApplicationInterface;
use Cake\Validation\Validator;
use CakeDisposableEmail\Command\CheckCommand;
use CakeDisposableEmail\Command\UpdateCommand;
use CakeDisposableEmail\Validation\DisposableEmailValidation;
use Wodzy\DisposableEmail\BlocklistInterface;

/**
 * CakeDisposableEmail plugin.
 *
 * - registers the `disposableEmail` validation provider on every Validator,
 * - exposes `Wodzy\DisposableEmail\BlocklistInterface` in the DI container,
 * - adds the `disposable_email update` and `disposable_email check` commands.
 */
class CakeDisposableEmailPlugin extends BasePlugin
{
    /**
     * Name of the validation provider registered by the plugin.
     */
    public const PROVIDER = 'disposableEmail';

    protected bool $middlewareEnabled = false;

    protected bool $routesEnabled = false;

    /**
     * @inheritDoc
     */
    public function bootstrap(PluginApplicationInterface $app): void
    {
        parent::bootstrap($app);

        // A class name (not an instance) keeps the provider lazy: the domain
        // lists are only loaded the first time a rule is actually evaluated.
        Validator::addDefaultProvider(self::PROVIDER, DisposableEmailValidation::class);
    }

    /**
     * @inheritDoc
     */
    public function console(CommandCollection $commands): CommandCollection
    {
        $commands->add(UpdateCommand::defaultName(), UpdateCommand::class);
        $commands->add(CheckCommand::defaultName(), CheckCommand::class);

        return $commands;
    }

    /**
     * @inheritDoc
     */
    public function services(ContainerInterface $container): void
    {
        $container->addShared(
            DisposableEmailConfig::class,
            fn(): DisposableEmailConfig => DisposableEmailConfig::fromConfigure(),
        );
        // Same instance as the one used by the validation rules.
        $container->addShared(BlocklistInterface::class, fn(): BlocklistInterface => DisposableEmailRegistry::get());

        $container->add(CheckCommand::class)->addArgument(BlocklistInterface::class);
        $container->add(UpdateCommand::class)->addArgument(DisposableEmailConfig::class);
    }
}
