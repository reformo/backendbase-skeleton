<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\UseCase\Console\Queue;

use Backendbase\Infrastructure\Adapters\Queue\ExternalIntegrationEventMessageProcessor;
use Backendbase\Shared\Integrations\BackendbaseQueue;
use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ContainerAwareQueueConsumer extends Command
{
    private const string COMMAND_NAME = 'queue-handler';

    public function __construct(
        private readonly BackendbaseQueue $queue,
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
        $messageParams = [
            'waitTimeSeconds' => 20,
            'queue' => $input->getArgument('name') ?? 'backendbase-queue',
        ];

        $this->queue->consume(
            $messageParams,
            fn (array $data) => $this->messageProcessor->process($data),
        );

        return self::SUCCESS;
    }
}
