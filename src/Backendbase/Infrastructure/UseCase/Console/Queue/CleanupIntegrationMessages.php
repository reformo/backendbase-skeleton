<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\UseCase\Console\Queue;

use Backendbase\Shared\Integrations\IntegrationMessageLogCleaner;
use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function ctype_digit;

final class CleanupIntegrationMessages extends Command
{
    private const int MAX_LIMIT = 10000;

    public function __construct(private readonly IntegrationMessageLogCleaner $cleaner)
    {
        parent::__construct('integration-messages:cleanup');
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->setDescription('Delete old published outbox and processed inbox messages.')
            ->addOption('retention-days', null, InputOption::VALUE_REQUIRED, 'Days to retain messages.', '30')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum rows per message log.', '1000');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $retentionDays = (string) $input->getOption('retention-days');
        $limit         = (string) $input->getOption('limit');
        if (! ctype_digit($retentionDays) || (int) $retentionDays < 30) {
            $output->writeln('<error>The retention period must be at least 30 days.</error>');

            return self::INVALID;
        }

        if (! ctype_digit($limit) || (int) $limit < 1 || (int) $limit > self::MAX_LIMIT) {
            $output->writeln('<error>The limit must be between 1 and ' . self::MAX_LIMIT . '.</error>');

            return self::INVALID;
        }

        $result = $this->cleaner->clean((int) $retentionDays, (int) $limit);
        $output->writeln(
            'Deleted outbox: ' . $result->outboxMessages()
            . '; inbox: ' . $result->inboxMessages()
            . '; delivery failures: ' . $result->deliveryFailures() . '.',
        );

        return self::SUCCESS;
    }
}
