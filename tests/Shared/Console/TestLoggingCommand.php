<?php

declare(strict_types=1);

namespace Tests\Shared\Console;

use Backendbase\Shared\Console\Command;
use Monolog\Logger;
use Override;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class TestLoggingCommand extends Command
{
    public function __construct(Logger $logger)
    {
        parent::__construct($logger, 'test:log');

        $this->setAliases(['test-log']);
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output;
        $this->logMessage('completed');

        return self::SUCCESS;
    }
}
