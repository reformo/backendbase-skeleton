<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Console\Queue;

use Backendbase\Infrastructure\Adapters\Queue\ExternalIntegrationEventMessageProcessor;
use Backendbase\Shared\Integrations\MessageConsumer;
use Backendbase\Shared\Integrations\Messaging\MessageSubscription;
use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ContainerAwareQueueConsumer extends Command
{
    private const string COMMAND_NAME = 'queue-handler';

    public function __construct(
        private readonly MessageConsumer $consumer,
        private readonly ExternalIntegrationEventMessageProcessor $messageProcessor,
    ) {
        parent::__construct(self::COMMAND_NAME);
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->setName('queue:consume')
            ->setDescription('Queue consumer')
            ->setDefinition([
                new InputArgument('name', InputArgument::OPTIONAL),
            ]);
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>----Queue consumer started-----</info>');
        $destination = $input->getArgument('name') ?? 'backendbase-queue';
        $this->consumer->consume(
            new MessageSubscription((string) $destination, 20),
            $this->messageProcessor->process(...),
        );

        return self::SUCCESS;
    }
}
