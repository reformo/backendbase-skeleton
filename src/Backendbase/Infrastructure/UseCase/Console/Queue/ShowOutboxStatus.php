<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\UseCase\Console\Queue;

use Backendbase\Shared\Integrations\OutboxMonitor;
use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function filter_var;

use const FILTER_VALIDATE_INT;

final class ShowOutboxStatus extends Command
{
    public function __construct(private readonly OutboxMonitor $monitor)
    {
        parent::__construct('outbox:status');
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->setDescription('Show pending and retried transactional outbox messages.')
            ->addOption(
                'max-pending-age',
                null,
                InputOption::VALUE_REQUIRED,
                'Fail when the oldest pending message exceeds this age in seconds.',
                '300',
            );
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $status            = $this->monitor->status();
        $maximumAgeSeconds = filter_var(
            $input->getOption('max-pending-age'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]],
        );
        if ($maximumAgeSeconds === false) {
            $output->writeln('<error>The maximum pending age must be a positive integer.</error>');

            return self::INVALID;
        }

        $output->writeln('Pending: ' . $status->pendingMessages() . '.');
        $output->writeln('Retried: ' . $status->retriedMessages() . '.');
        $output->writeln('Oldest pending: ' . ($status->oldestPendingAt() ?? 'none') . '.');
        if ($status->hasPendingMessageOlderThan($maximumAgeSeconds)) {
            $output->writeln('<error>The oldest pending message exceeds the maximum age.</error>');

            return self::FAILURE;
        }

        if ($status->retriedMessages() > 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
