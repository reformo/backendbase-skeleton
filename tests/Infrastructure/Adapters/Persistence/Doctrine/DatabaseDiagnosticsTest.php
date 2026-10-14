<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Persistence\Doctrine;

use Backendbase\Infrastructure\Adapters\Console\Operations\InspectDatabase;
use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\Operations\MySqlDiagnostics;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQL84Platform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;

use function json_decode;

use const JSON_THROW_ON_ERROR;

final class DatabaseDiagnosticsTest extends TestCase
{
    public function testDiagnosticsReturnAggregateMetricsWithoutSqlTextOrRowData(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new MySQL84Platform());
        $connection->method('fetchAssociative')->willReturnOnConsecutiveCalls(['serverVersion' => '8.4.10', 'characterSet' => 'utf8mb4', 'collation' => 'utf8mb4_tr_0900_ai_ci'], ['dataBytes' => '4096', 'indexBytes' => '2048']);
        $connection->method('fetchOne')->willReturn('0');
        $connection->method('fetchAllAssociative')->willReturn([['NAME' => 'lock_deadlocks', 'metricCount' => '2']]);
        $tester = new CommandTester(new InspectDatabase(new MySqlDiagnostics($connection)));
        self::assertSame(0, $tester->execute([]));
        self::assertSame(['serverVersion' => '8.4.10', 'characterSet' => 'utf8mb4', 'collation' => 'utf8mb4_tr_0900_ai_ci', 'foreignKeys' => 0, 'lock_deadlocks' => 2, 'dataBytes' => 4096, 'indexBytes' => 2048], json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string, mixed>|false $environment
     * @param array<string, mixed>|false $sizes
     */
    #[DataProvider('invalidResponses')]
    public function testUnsupportedOrMalformedDiagnosticResponsesFailExplicitly(array|false $environment, array|false $sizes, mixed $count): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new MySQL84Platform());
        $connection->method('fetchAssociative')->willReturnOnConsecutiveCalls($environment, $sizes);
        $connection->method('fetchOne')->willReturn($count);
        $connection->method('fetchAllAssociative')->willReturn([]);
        $this->expectException(RuntimeException::class);
        new MySqlDiagnostics($connection)->inspect();
    }

    /** @return iterable<string, array{array<string, mixed>|false, array<string, mixed>|false, mixed}> */
    public static function invalidResponses(): iterable
    {
        $valid = ['serverVersion' => '8.4.10', 'characterSet' => 'utf8mb4', 'collation' => 'utf8mb4_tr_0900_ai_ci'];

        yield 'missing environment' => [false, false, 0];
        yield 'wrong MySQL version' => [[...$valid, 'serverVersion' => '8.0.1'], false, 0];
        yield 'invalid charset response' => [[...$valid, 'characterSet' => null], false, 0];
        yield 'missing sizes' => [$valid, false, 0];
        yield 'invalid integer type' => [$valid, false, []];
        yield 'invalid integer value' => [$valid, false, 'invalid'];
        yield 'negative counter' => [$valid, false, -1];
    }

    public function testOtherDatabasePlatformsAreRejected(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new SQLitePlatform());
        $this->expectException(RuntimeException::class);
        new MySqlDiagnostics($connection)->inspect();
    }
}
