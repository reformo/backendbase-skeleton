<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\CommandHandlers;

use Backendbase\Domain\IdentityAndAccess\Contracts\AccountAuthorizationState;
use Backendbase\Domain\IdentityAndAccess\Contracts\AccountWriteRepository;
use Backendbase\Domain\IdentityAndAccess\Contracts\Command\RetireAccount;
use Backendbase\Shared\CQRS\Command;
use Backendbase\Shared\CQRS\CommandHandler;
use Override;

final readonly class RetireAccountHandler implements CommandHandler
{
    public const string REQUIRED_PRIVILEGE = 'account.retire';

    public function __construct(
        private AccountWriteRepository $accountRepository,
        private AccountAuthorizationState $authorizationState,
    ) {
    }

    /** @param RetireAccount $command */
    #[Override]
    public function handle(Command $command): void
    {
        $command->accessControl()->isAllowed(self::REQUIRED_PRIVILEGE);
        $account = $this->accountRepository->getActive($command->accountId());
        $this->authorizationState->revokeAll($account->id());
        $account->retire();
        $this->accountRepository->save($account);
    }
}
