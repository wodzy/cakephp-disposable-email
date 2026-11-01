<?php
declare(strict_types=1);

namespace CakeDisposableEmail\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Wodzy\DisposableEmail\BlocklistInterface;
use Wodzy\DisposableEmail\Exception\InvalidEmailException;

/**
 * `bin/cake disposable_email check <email>`: tells whether an address would be blocked.
 *
 * Exit codes: 0 allowed, 1 blocked, 2 not a valid email address.
 */
class CheckCommand extends Command
{
    /**
     * Exit code when the argument is not a valid email address.
     */
    public const CODE_INVALID = 2;

    /**
     * @param \Wodzy\DisposableEmail\BlocklistInterface $blocklist Blocklist to check against.
     */
    public function __construct(private readonly BlocklistInterface $blocklist)
    {
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    public static function defaultName(): string
    {
        return 'disposable_email check';
    }

    /**
     * @inheritDoc
     */
    public static function getDescription(): string
    {
        return 'Check whether an email address belongs to a blocked domain.';
    }

    /**
     * @inheritDoc
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser
            ->setDescription(static::getDescription() . ' Exit codes: 0 allowed, 1 blocked, 2 invalid.')
            ->addArgument('email', ['help' => 'Email address to check.', 'required' => true]);
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $email = (string)$args->getArgument('email');

        try {
            $blocked = $this->blocklist->blocks($email);
        } catch (InvalidEmailException $e) {
            $io->error($e->getMessage());

            return self::CODE_INVALID;
        }

        if ($blocked) {
            $io->warning(sprintf('%s is blocked: its domain is on the block list.', $email));

            return static::CODE_ERROR;
        }

        $io->success(sprintf('%s is allowed.', $email));

        return static::CODE_SUCCESS;
    }
}
