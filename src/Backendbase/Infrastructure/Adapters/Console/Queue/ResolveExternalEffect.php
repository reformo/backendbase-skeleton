<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Console\Queue;

use Backendbase\Shared\Persistence\ExternalEffectResolution;
use Backendbase\Shared\Persistence\ExternalEffectResolver;
use Backendbase\Shared\Persistence\RecoveryEvidence;
use InvalidArgumentException;
use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function is_string;

final class ResolveExternalEffect extends Command
{
    public function __construct(private readonly ExternalEffectResolver $resolver)
    {
        parent::__construct('external-effect:resolve');
    }

    #[Override]
    protected function configure(): void
    {
        $this->setDescription('Preview or record provider evidence for an uncertain external effect.');
        foreach (['consumer', 'message-id', 'decision', 'operator', 'evidence'] as $name) {
            $this->addOption($name, null, InputOption::VALUE_REQUIRED);
        }

        $this->addOption('apply', null, InputOption::VALUE_NONE, 'Record the decision. Only confirmed nonacceptance permits another attempt.');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $resolution = ExternalEffectResolution::tryFrom(self::option($input, 'decision'));
            if ($resolution === null) {
                throw new InvalidArgumentException('The decision must be accepted or not-accepted.');
            }

            $evidence = new RecoveryEvidence(self::option($input, 'operator'), self::option($input, 'evidence'));
            $this->resolver->resolve(
                self::option($input, 'consumer'),
                self::option($input, 'message-id'),
                $resolution,
                $evidence,
                (bool) $input->getOption('apply'),
            );
        } catch (InvalidArgumentException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');

            return self::INVALID;
        }

        $output->writeln($input->getOption('apply') ? 'The outcome and evidence reference were recorded.' : 'Preview passed. No record changed.');

        return self::SUCCESS;
    }

    private static function option(InputInterface $input, string $name): string
    {
        $value = $input->getOption($name);
        if (! is_string($value) || $value === '') {
            throw new InvalidArgumentException('Supply --' . $name . '.');
        }

        return $value;
    }
}
