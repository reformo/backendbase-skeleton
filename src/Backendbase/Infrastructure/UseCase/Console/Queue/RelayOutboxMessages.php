<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\UseCase\Console\Queue;

use Backendbase\Shared\Integrations\OutboxRelay;
use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function ctype_digit;

final class RelayOutboxMessages extends Command
{
    private const int MAX_LIMIT = 1000;

    public function __construct(private readonly OutboxRelay $outboxRelay)
    {
        parent::__construct('outbox:relay');
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->setDescription('Publish pending transactional outbox messages.')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum messages to process.', '100');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = (string) $input->getOption('limit');
        if (! ctype_digit($limit) || (int) $limit < 1 || (int) $limit > self::MAX_LIMIT) {
            $output->writeln('<error>The limit must be between 1 and ' . self::MAX_LIMIT . '.</error>');

            return self::INVALID;
        }

        $result = $this->outboxRelay->relay((int) $limit);
        $output->writeln('Published: ' . $result->published() . '; failed: ' . $result->failed() . '.');
        if ($result->failed() > 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
