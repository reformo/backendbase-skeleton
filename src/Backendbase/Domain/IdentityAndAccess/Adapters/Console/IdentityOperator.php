<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Console;

use Backendbase\Domain\IdentityAndAccess\Application\IdentityOperation;
use Backendbase\Domain\IdentityAndAccess\Contracts\Command\Operations\ApplyOperatorAction;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\OperatorInput;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Shared\Domain\Exception\DomainException;
use InvalidArgumentException;
use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function function_exists;
use function is_string;
use function posix_geteuid;

use const PHP_SAPI;

final class IdentityOperator extends Command
{
    public function __construct(private readonly IdentityOperation $operation)
    {
        parent::__construct('identity:operator');
    }

    #[Override]
    protected function configure(): void
    {
        $this->setDescription('Preview local first-Admin setup, TOTP recovery, or persisted factor key rotation.');
        foreach (['action', 'target-id', 'operator', 'invitation', 'approval', 'login-name'] as $name) {
            $this->addOption($name, null, InputOption::VALUE_REQUIRED);
        }

        $this->addOption('apply', null, InputOption::VALUE_NONE, 'Apply the action in an authorized local root CLI process.');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $apply = (bool) $input->getOption('apply');
        try {
            if ($apply) {
                LocalOperatorAuthority::assertRoot(PHP_SAPI, function_exists('posix_geteuid') ? posix_geteuid() : null);
            }

            $request     = new OperatorInput(self::option($input, 'action'), self::option($input, 'target-id'), self::option($input, 'operator'), self::option($input, 'invitation'), self::option($input, 'approval'), self::option($input, 'login-name'));
            $operationId = IdentityId::create();
            $identifier  = $operationId->toString();
            $command     = new ApplyOperatorAction($identifier, $request, $apply);
            $operation   = $this->operation;
            $result      = $operation->execute($command);
            $data        = $result->data();
            $target      = $data['targetId'];
        } catch (InvalidArgumentException | DomainException) {
            $output->writeln('<error>The operator action was rejected. Check authority, input, and current identity state.</error>');

            return self::INVALID;
        }

        $output->writeln($apply ? 'The action and evidence were recorded for ' . $target . '.' : 'Preview passed. No identity record changed.');

        return self::SUCCESS;
    }

    private static function option(InputInterface $input, string $name): string
    {
        $value = $input->getOption($name);
        if ($value === null) {
            return '';
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException('Operator options must be strings.');
        }

        return $value;
    }
}
