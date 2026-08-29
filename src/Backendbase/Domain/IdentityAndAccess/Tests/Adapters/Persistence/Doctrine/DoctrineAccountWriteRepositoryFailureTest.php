<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Adapters\Persistence\Doctrine;

use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\DoctrineAccountWriteRepository;
use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\Entity\AccountRecord;
use Backendbase\Domain\IdentityAndAccess\Domain\Account;
use Backendbase\Domain\IdentityAndAccess\Domain\AccountId;
use Backendbase\Domain\IdentityAndAccess\Domain\AccountPrivileges;
use Backendbase\Domain\IdentityAndAccess\Exception\AccountAlreadyRegistered;
use Backendbase\Shared\Primitives\Email;
use Backendbase\Shared\Primitives\PasswordHash;
use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DoctrineAccountWriteRepositoryFailureTest extends TestCase
{
    #[Test]
    public function itTranslatesRegistrationConstraintViolations(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $repository    = $this->createStub(EntityRepository::class);
        $repository->method('findOneBy')->willReturn(null);
        $entityManager->method('getRepository')->willReturn($repository);
        $entityManager->expects(self::once())->method('persist');
        $entityManager->expects(self::once())->method('flush')->willThrowException($this->constraintViolation());

        $this->expectException(AccountAlreadyRegistered::class);

        (new DoctrineAccountWriteRepository($entityManager))->register($this->account());
    }

    #[Test]
    public function itTranslatesRevisionConstraintViolations(): void
    {
        $account       = $this->account();
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $repository    = $this->createStub(EntityRepository::class);
        $repository->method('findOneBy')->willReturn(AccountRecord::fromDomain($account));
        $entityManager->method('getRepository')->willReturn($repository);
        $entityManager->expects(self::once())->method('flush')->willThrowException($this->constraintViolation());

        $this->expectException(AccountAlreadyRegistered::class);

        (new DoctrineAccountWriteRepository($entityManager))->save($account);
    }

    private function account(): Account
    {
        return Account::register(
            AccountId::fromString('4bb3fe29-8b80-463e-9d42-b3a9298a7586'),
            new Email('account@example.com'),
            PasswordHash::create('password-hash'),
            new AccountPrivileges([]),
        );
    }

    private function constraintViolation(): UniqueConstraintViolationException
    {
        $driverException = new class ('Unique constraint violation') extends RuntimeException implements DriverException {
            public function getSQLState(): string
            {
                return '23000';
            }
        };

        return new UniqueConstraintViolationException($driverException, null);
    }
}
