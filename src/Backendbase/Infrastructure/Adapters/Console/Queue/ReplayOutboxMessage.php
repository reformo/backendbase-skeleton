<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Console\Queue;

use Backendbase\Shared\Integrations\OutboxReplay;
use Backendbase\Shared\Persistence\RecoveryEvidence;
use InvalidArgumentException;
use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function is_string;

final class ReplayOutboxMessage extends Command
{
    public function __construct(private readonly OutboxReplay $replay)
    {
        parent::__construct('outbox:replay');
    }

    #[Override]
    protected function configure(): void
    {
        $this->setDescription('Preview or schedule one replay with its original identity and occurrence time.');
        foreach (['message-id', 'operator', 'evidence'] as $name) {
            $this->addOption($name, null, InputOption::VALUE_REQUIRED);
        }

        $this->addOption('apply', null, InputOption::VALUE_NONE, 'Record and schedule the replay.');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $evidence = new RecoveryEvidence(self::option($input, 'operator'), self::option($input, 'evidence'));
            $this->replay->schedule(self::option($input, 'message-id'), $evidence, (bool) $input->getOption('apply'));
        } catch (InvalidArgumentException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');

            return self::INVALID;
        }

        $output->writeln($input->getOption('apply') ? 'The replay was recorded and scheduled.' : 'Preview passed. No record changed.');

        return self::SUCCESS;
    }

    private static function option(InputInterface $input, string $name): string
    {
        $value = $input->getOption($name);
        if (! is_string($value) || $value === '') {
            throw new InvalidArgumentException('Supply --' . $name . '.');
        }

        return $value;
    }
}
