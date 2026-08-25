<?php

declare(strict_types=1);

namespace Backendbase\Shared\Console;

use Backendbase\Shared\Helpers\DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Output\OutputInterface;

class Command extends SymfonyCommand
{
    protected OutputInterface $output;

    public function __construct(
        private readonly LoggerInterface $logger,
        string $name,
    ) {
        parent::__construct($name);
    }

    protected function logMessage(string $message): void
    {
        $now     = DateTimeImmutable::create()->format('Y-m-d H:i:s');
        $message = $now . ': CLI/' . $this->getAliases()[0] . ' - ' . $message;
        $this->logger->debug($message);
        $this->output->writeln($message);
    }
}
