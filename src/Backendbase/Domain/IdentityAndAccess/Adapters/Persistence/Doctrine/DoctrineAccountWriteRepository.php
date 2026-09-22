<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine;

use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\Entity\AccountRecord;
use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\Entity\PrivilegeRecord;
use Backendbase\Domain\IdentityAndAccess\Contracts\AccountWriteRepository as AccountWriteRepositoryContract;
use Backendbase\Domain\IdentityAndAccess\Domain\Account;
use Backendbase\Domain\IdentityAndAccess\Domain\AccountId;
use Backendbase\Domain\IdentityAndAccess\Domain\AccountPrivileges;
use Backendbase\Domain\IdentityAndAccess\Exception\AccountAlreadyRegistered;
use Backendbase\Domain\IdentityAndAccess\Exception\UnknownAccountPrivilege;
use Backendbase\Shared\Exception\ResourceNotFound;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Throwable;

use function count;

final readonly class DoctrineAccountWriteRepository implements AccountWriteRepositoryContract
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /** @param callable(): void $change */
    public function withAccountLock(AccountId $accountId, callable $change): void
    {
        $connection = $this->entityManager->getConnection();
        $lock       = new DoctrineAccountLock($connection);

        try {
            $lock->forAccount($accountId, $change);
        } catch (Throwable $exception) {
            $this->entityManager->close();

            throw $exception;
        }
    }

    public function register(Account $account): void
    {
        $this->rejectActiveEmail($account);
        $record = AccountRecord::fromDomain($account);
        $record->replacePrivileges($this->activePrivileges($account->privileges()));

        try {
            $this->entityManager->persist($record);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $exception) {
            throw AccountAlreadyRegistered::create(
                'An active account already uses this email address.',
                previous: $exception,
            );
        }
    }

    public function getActive(AccountId $accountId): Account
    {
        $record = $this->activeRecord($accountId);
        $this->entityManager->refresh($record);

        return $record->toDomain();
    }

    public function save(Account $account): void
    {
        $record = $this->activeRecord($account->id());
        $record->synchronize($account);
        $record->replacePrivileges($this->activePrivileges($account->privileges()));

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $exception) {
            throw AccountAlreadyRegistered::create(
                'An active account already uses this email address.',
                previous: $exception,
            );
        }
    }

    private function activeRecord(AccountId $accountId): AccountRecord
    {
        $repository = $this->entityManager->getRepository(AccountRecord::class);
        $record     = $repository->findOneBy([
            'uuid' => $accountId->toString(),
            'deletedAt' => null,
        ]);
        if (! $record instanceof AccountRecord) {
            throw ResourceNotFound::create('The account does not exist.');
        }

        return $record;
    }

    /** @return list<PrivilegeRecord> */
    private function activePrivileges(AccountPrivileges $privileges): array
    {
        $slugs = $privileges->slugs();
        if ($slugs === []) {
            return [];
        }

        $repository = $this->entityManager->getRepository(PrivilegeRecord::class);
        $records    = $repository->findBy(['slug' => $slugs, 'deletedAt' => null]);
        if (count($records) !== count($slugs)) {
            throw UnknownAccountPrivilege::create('An account privilege is unavailable.');
        }

        return $records;
    }

    private function rejectActiveEmail(Account $account): void
    {
        $repository = $this->entityManager->getRepository(AccountRecord::class);
        $existing   = $repository->findOneBy([
            'email' => $account->email()->toString(),
            'deletedAt' => null,
        ]);
        if ($existing instanceof AccountRecord) {
            throw AccountAlreadyRegistered::create('An active account already uses this email address.');
        }
    }
}
