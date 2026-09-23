<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\UseCase\Console\Queue;

use Backendbase\Shared\Integrations\Operation\OutboxRelayResult;
use Backendbase\Shared\Integrations\OutboxRelay;
use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function ctype_digit;
use function usleep;

final class RelayOutboxMessages extends Command
{
    private const int MAX_LIMIT              = 1000;
    private const int IDLE_POLL_MICROSECONDS = 250_000;

    public function __construct(private readonly OutboxRelay $outboxRelay)
    {
        parent::__construct('outbox:relay');
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->setDescription('Publish pending transactional outbox messages.')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum messages per batch.', '100')
            ->addOption('continuous', null, InputOption::VALUE_NONE, 'Keep relaying with a 250 ms idle poll.');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = (string) $input->getOption('limit');
        if (! ctype_digit($limit) || (int) $limit < 1 || (int) $limit > self::MAX_LIMIT) {
            $output->writeln('<error>The limit must be between 1 and ' . self::MAX_LIMIT . '.</error>');

            return self::INVALID;
        }

        if ($input->getOption('continuous') === true) {
            $this->relayContinuously((int) $limit, $output);
        }

        $result = $this->outboxRelay->relay((int) $limit);
        $this->reportResult($result, $output);
        if ($result->failed() > 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function relayContinuously(int $limit, OutputInterface $output): never
    {
        $output->writeln('<info>Outbox relay started. Idle poll: 250 ms.</info>');
        $outboxRelay = $this->outboxRelay;

        while (true) {
            $result    = $outboxRelay->relay($limit);
            $published = $result->published();
            $failed    = $result->failed();
            if ($published > 0 || $failed > 0) {
                $this->reportResult($result, $output);
            }

            if ($published + $failed >= $limit) {
                continue;
            }

            usleep(self::IDLE_POLL_MICROSECONDS);
        }
    }

    private function reportResult(OutboxRelayResult $result, OutputInterface $output): void
    {
        $published = $result->published();
        $failed    = $result->failed();
        $output->writeln('Published: ' . $published . '; failed: ' . $failed . '.');
    }
}
