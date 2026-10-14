<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Console\Operations;

use Backendbase\Shared\Health\DatabaseDiagnostics;
use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function json_encode;

use const JSON_THROW_ON_ERROR;

final class InspectDatabase extends Command
{
    public function __construct(private readonly DatabaseDiagnostics $diagnostics)
    {
        parent::__construct('database:diagnostics');
    }

    #[Override]
    protected function configure(): void
    {
        $this->setDescription('Read MySQL version, collation, lock, buffer, and index metrics.');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $diagnostics = $this->diagnostics;
        $result      = $diagnostics->inspect();
        $output->writeln(json_encode($result, JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
