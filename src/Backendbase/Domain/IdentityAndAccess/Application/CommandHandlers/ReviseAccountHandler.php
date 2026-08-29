<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\CommandHandlers;

use Backendbase\Domain\IdentityAndAccess\Contracts\AccountAuthorizationState;
use Backendbase\Domain\IdentityAndAccess\Contracts\AccountWriteRepository;
use Backendbase\Domain\IdentityAndAccess\Contracts\Command\ReviseAccount;
use Backendbase\Shared\CQRS\Command;
use Backendbase\Shared\CQRS\CommandHandler;
use Override;

final readonly class ReviseAccountHandler implements CommandHandler
{
    public const string REQUIRED_PRIVILEGE = 'account.revise';

    public function __construct(
        private AccountWriteRepository $accountRepository,
        private AccountAuthorizationState $authorizationState,
    ) {
    }

    /** @param ReviseAccount $command */
    #[Override]
    public function handle(Command $command): void
    {
        $command->accessControl()->isAllowed(self::REQUIRED_PRIVILEGE);
        $account = $this->accountRepository->getActive($command->accountId());
        $this->authorizationState->revokeAll($account->id());
        $account->revise($command->email(), $command->passwordHash(), $command->privileges());
        $this->accountRepository->save($account);
    }
}
