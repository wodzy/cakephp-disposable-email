<?php
declare(strict_types=1);

namespace CakeDisposableEmail\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use CakeDisposableEmail\DisposableEmailConfig;
use CakeDisposableEmail\DomainList\ListUpdater;
use RuntimeException;

/**
 * `bin/cake disposable_email update`: refreshes the downloaded disposable-domain list.
 */
class UpdateCommand extends Command
{
    /**
     * @param \CakeDisposableEmail\DisposableEmailConfig $config Plugin configuration.
     */
    public function __construct(private readonly DisposableEmailConfig $config)
    {
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    public static function defaultName(): string
    {
        return 'disposable_email update';
    }

    /**
     * @inheritDoc
     */
    public static function getDescription(): string
    {
        return 'Download the latest disposable email domain list.';
    }

    /**
     * @inheritDoc
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser
            ->setDescription(static::getDescription())
            ->addOption('url', ['help' => 'Override the "DisposableEmail.updateUrl" source.']);
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $url = $args->getOption('url');
        $config = is_string($url) && $url !== '' ? $this->config->withUpdateUrl($url) : $this->config;

        $io->out(sprintf('Downloading <info>%s</info>', $config->updateUrl));

        try {
            $result = (new ListUpdater($config))->update();
        } catch (RuntimeException $e) {
            $io->error($e->getMessage());

            return static::CODE_ERROR;
        }

        $io->success(sprintf('%d domains written to %s', $result->count, $result->path));
        if ($result->skipped !== []) {
            $io->out(sprintf('Skipped protected providers: %s', implode(', ', $result->skipped)));
        }
        if ($result->invalidLines > 0) {
            $io->verbose(sprintf('Ignored %d invalid lines.', $result->invalidLines));
        }

        return static::CODE_SUCCESS;
    }
}
