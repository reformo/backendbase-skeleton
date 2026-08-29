<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\CommandHandlers;

use Backendbase\Domain\IdentityAndAccess\Contracts\AccountWriteRepository;
use Backendbase\Domain\IdentityAndAccess\Contracts\Command\RegisterAccount;
use Backendbase\Domain\IdentityAndAccess\Domain\Account;
use Backendbase\Shared\CQRS\Command;
use Backendbase\Shared\CQRS\CommandHandler;
use Override;

final readonly class RegisterAccountHandler implements CommandHandler
{
    public const string REQUIRED_PRIVILEGE = 'account.register';

    public function __construct(private AccountWriteRepository $accountRepository)
    {
    }

    /** @param RegisterAccount $command */
    #[Override]
    public function handle(Command $command): void
    {
        $command->accessControl()->isAllowed(self::REQUIRED_PRIVILEGE);
        $account = Account::register(
            $command->accountId(),
            $command->email(),
            $command->passwordHash(),
            $command->privileges(),
        );
        $this->accountRepository->register($account);
    }
}
