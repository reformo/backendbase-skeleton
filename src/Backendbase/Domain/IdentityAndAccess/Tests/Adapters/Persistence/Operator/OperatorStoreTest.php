<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Adapters\Persistence\Operator;

use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Operator\DoctrineOperatorStore;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\IdentityConflict;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class OperatorStoreTest extends TestCase
{
    #[DataProvider('operationOutcomes')]
    public function testSetupReleasesTheLockAfterSuccessOrFailure(bool $fails): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new MySQLPlatform());
        $connection->expects(self::exactly(2))->method('fetchOne')->willReturnCallback(static function (string $sql): int {
            self::assertMatchesRegularExpression('/SELECT (GET_LOCK|RELEASE_LOCK)\(/', $sql);

            return 1;
        });
        $store = new DoctrineOperatorStore($connection);
        if ($fails) {
            $this->expectExceptionMessage('Operation failed.');
        }

        self::assertSame('completed', $store->exclusive(static function () use ($fails): string {
            if ($fails) {
                throw new RuntimeException('Operation failed.');
            }

            return 'completed';
        }));
    }

    /** @return iterable<string, array{bool}> */
    public static function operationOutcomes(): iterable
    {
        yield 'success' => [false];
        yield 'failure' => [true];
    }

    #[DataProvider('unavailableLocks')]
    public function testAnUnavailableLockStopsSetupBeforeAnyWrite(int|null $result): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new MySQLPlatform());
        $connection->expects(self::once())->method('fetchOne')->with(self::stringContains('GET_LOCK('))->willReturn($result);
        $store = new DoctrineOperatorStore($connection);
        $this->expectException(IdentityConflict::class);
        $store->exclusive(static fn (): never => self::fail('An unlocked setup must not run.'));
    }

    /** @return iterable<string, array{int|null}> */
    public static function unavailableLocks(): iterable
    {
        yield 'timeout' => [0];
        yield 'lock error' => [null];
    }

    public function testAFailedLockReleaseIsReported(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new MySQLPlatform());
        $connection->expects(self::exactly(2))->method('fetchOne')->willReturn(1, 0);
        $store = new DoctrineOperatorStore($connection);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The first-Admin setup lock could not be released.');
        $store->exclusive(static fn (): string => 'completed');
    }
}
