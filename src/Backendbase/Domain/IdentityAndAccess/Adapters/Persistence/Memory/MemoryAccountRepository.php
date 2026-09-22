<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Memory;

use Backendbase\Domain\IdentityAndAccess\Contracts\AccountAuthentication;
use Backendbase\Domain\IdentityAndAccess\Contracts\AccountAuthenticationRepository;
use Backendbase\Domain\IdentityAndAccess\Contracts\AccountReadRepository;
use Backendbase\Domain\IdentityAndAccess\Contracts\AccountWriteRepository;
use Backendbase\Domain\IdentityAndAccess\Contracts\ReadModel\AccountListItem;
use Backendbase\Domain\IdentityAndAccess\Domain\Account;
use Backendbase\Domain\IdentityAndAccess\Domain\AccountId;
use Backendbase\Domain\IdentityAndAccess\Exception\AccountAlreadyRegistered;
use Backendbase\Shared\Exception\ResourceNotFound;
use Backendbase\Shared\Helpers\DateTimeImmutable;
use Throwable;

use function strcmp;
use function usort;

final class MemoryAccountRepository implements
    AccountAuthenticationRepository,
    AccountReadRepository,
    AccountWriteRepository
{
    /** @var array<string, array{account: Account, createdAt: \DateTimeImmutable}> */
    private array $records = [];

    /** @param callable(): string $authenticate */
    public function withAuthenticationLock(string $email, callable $authenticate): string
    {
        return $authenticate();
    }

    /** @param callable(): void $change */
    public function withAccountLock(AccountId $accountId, callable $change): void
    {
        $records = $this->records;
        try {
            $change();
        } catch (Throwable $exception) {
            $this->records = $records;

            throw $exception;
        }
    }

    public function register(Account $account): void
    {
        $this->rejectActiveEmail($account);
        $this->records[$account->id()->toString()] = [
            'account' => clone $account,
            'createdAt' => DateTimeImmutable::create(),
        ];
    }

    public function getActive(AccountId $accountId): Account
    {
        $record = $this->records[$accountId->toString()] ?? null;
        if ($record === null || $record['account']->isRetired()) {
            throw ResourceNotFound::create('The account does not exist.');
        }

        return clone $record['account'];
    }

    public function save(Account $account): void
    {
        $key    = $account->id()->toString();
        $record = $this->records[$key] ?? null;
        if ($record === null || $record['account']->isRetired()) {
            throw ResourceNotFound::create('The account does not exist.');
        }

        $this->rejectActiveEmail($account);
        $this->records[$key]['account'] = clone $account;
    }

    /** @return list<AccountListItem> */
    public function listActive(): array
    {
        $items = [];
        foreach ($this->records as $record) {
            $account = $record['account'];
            if ($account->isRetired()) {
                continue;
            }

            $items[] = new AccountListItem(
                $account->id()->toString(),
                $account->email()->toString(),
                $account->privileges()->slugs(),
                $record['createdAt'],
            );
        }

        usort($items, static fn (AccountListItem $left, AccountListItem $right): int => strcmp(
            $left->email(),
            $right->email(),
        ));

        return $items;
    }

    public function findByEmail(string $email): AccountAuthentication|null
    {
        foreach ($this->records as $record) {
            $account = $record['account'];
            if ($account->isRetired() || $account->email()->toString() !== $email) {
                continue;
            }

            return new AccountAuthentication(
                $account->id()->toString(),
                $account->email()->toString(),
                $account->passwordHash()->toString(),
                $account->privileges()->slugs(),
            );
        }

        return null;
    }

    private function rejectActiveEmail(Account $candidate): void
    {
        foreach ($this->records as $record) {
            $account = $record['account'];
            if ($account->id()->toString() === $candidate->id()->toString()) {
                continue;
            }

            if (! $account->isRetired() && $account->email()->toString() === $candidate->email()->toString()) {
                throw AccountAlreadyRegistered::create('An active account already uses this email address.');
            }
        }
    }
}
