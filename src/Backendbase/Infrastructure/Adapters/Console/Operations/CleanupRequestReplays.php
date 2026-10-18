<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Console\Operations;

use Backendbase\Shared\Idempotency\RequestReplayStore;
use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class CleanupRequestReplays extends Command
{
    public function __construct(private readonly RequestReplayStore $store)
    {
        parent::__construct('idempotency:cleanup');
    }

    #[Override]
    protected function configure(): void
    {
        $this->setDescription('Delete up to 500 expired completed request responses. Preserve unresolved claims.');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $store = $this->store;
        $count = $store->deleteExpired();
        $output->writeln('Deleted request responses: ' . $count . '.');

        return self::SUCCESS;
    }
}
