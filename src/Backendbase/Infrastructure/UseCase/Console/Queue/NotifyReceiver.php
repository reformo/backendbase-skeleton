<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\UseCase\Console\Queue;

use Backendbase\Infrastructure\Adapters\Queue\NotificationMessageProcessor;
use Backendbase\Shared\Integrations\BackendbaseQueue;
use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class NotifyReceiver extends Command
{
    private const string COMMAND_NAME = 'notifier';

    public function __construct(
        private readonly BackendbaseQueue $queue,
        private readonly NotificationMessageProcessor $messageProcessor,
    ) {
        parent::__construct(self::COMMAND_NAME);
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->setName('queue:notify-consumer')
            ->setDescription('Queue consumer')
            ->setDefinition([
                new InputArgument('name', InputArgument::OPTIONAL),
            ]);
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>----Notifier started consuming-----</info>');
        $messageParams = [
            'waitTimeSeconds' => 20,
            'queueName' => $input->getArgument('name') ?? 'backendbase-queue-email',
        ];
        $this->queue->consume($messageParams, $this->messageProcessor->process(...));

        return self::SUCCESS;
    }
}
