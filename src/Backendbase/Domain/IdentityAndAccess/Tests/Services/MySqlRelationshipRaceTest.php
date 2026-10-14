<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Services;

use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityReadRepository;
use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityStore;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Challenges\Challenge;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\LoginName;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use PHPUnit\Framework\TestCase;
use Tests\Support\Services\IdentityServiceFixture;
use Tests\Support\Services\IsolatedMySql;
use Tests\Support\Services\MySqlWorker;

use function getenv;

final class MySqlRelationshipRaceTest extends TestCase
{
    private IsolatedMySql $database;
    private IdentityFixture $identity;

    protected function setUp(): void
    {
        if (getenv('BACKENDBASE_SERVICE_TESTS') !== '1') {
            self::markTestSkipped('An explicitly prepared MySQL 8.4 target is required.');
        }

        $this->database = IsolatedMySql::create();
        $this->identity = IdentityServiceFixture::create($this->database->connection());
        IdentityScenario::registered($this->identity);
    }

    protected function tearDown(): void
    {
        if (! isset($this->database)) {
            return;
        }

        $this->database->destroy();
    }

    public function testUserRemovalWaitsUntilTheReferencingChallengeCommits(): void
    {
        $challenge  = $this->challenge();
        $state      = $challenge->snapshot();
        $connection = $this->database->connection();
        $worker     = MySqlWorker::start($connection, 'remove-user', [$state['accountId'], $state['actorId']]);
        try {
            $workerId = (int) $worker->receive();
            $store    = $this->store();
            $store->transaction(static function () use ($store, $challenge, $worker, $workerId, $connection): void {
                $store->saveChallenge($challenge);
                $worker->send('begin');
                $worker->awaitLockWait($connection, $workerId);
            });
            self::assertSame('locked', $worker->receive());
            $worker->send('commit');
            self::assertSame('committed', $worker->receive());
            self::assertSame(1, $connection->fetchOne('SELECT COUNT(*) FROM identity_challenge WHERE id = UUID_TO_BIN(?, 0) AND actor_id = UUID_TO_BIN(?, 0)', [$challenge->id(), $state['actorId']]));
            self::assertSame(0, $connection->fetchOne('SELECT COUNT(*) FROM identity_challenge c LEFT JOIN identity_user u ON u.id = c.actor_id WHERE u.id IS NULL'));
            self::assertNull($store->transaction(static fn () => $store->actorByLogin(Realm::USER, new LoginName('ada.user'))));
            $container = $this->identity->container();
            $history   = $container->get(IdentityReadRepository::class);
            $actor     = $history->actorHistory(Realm::USER, $state['actorId']);
            self::assertNotNull($actor);
            self::assertSame('2026-10-14T10:01:00.000000Z', $actor->jsonSerialize()['deletedAt']);
        } finally {
            $worker->close();
        }
    }

    public function testCommittedUserRemovalRejectsANewReferenceFromAnEarlierSnapshot(): void
    {
        $challenge  = $this->challenge();
        $state      = $challenge->snapshot();
        $connection = $this->database->connection();
        $worker     = MySqlWorker::start($connection, 'remove-user', [$state['accountId'], $state['actorId']]);
        try {
            self::assertGreaterThan(0, (int) $worker->receive());
            $worker->send('begin');
            self::assertSame('locked', $worker->receive());
            $worker->send('commit');
            self::assertSame('committed', $worker->receive());
            $store = $this->store();
            try {
                $store->transaction(static fn () => $store->saveChallenge($challenge));
                self::fail('A removed User must not receive a new challenge reference.');
            } catch (AuthenticationDenied $exception) {
                self::assertSame('Authentication is not permitted.', $exception->getMessage());
            }

            self::assertSame(0, $connection->fetchOne('SELECT COUNT(*) FROM identity_challenge WHERE id = UUID_TO_BIN(?, 0)', [$challenge->id()]));
            self::assertFalse($connection->isTransactionActive());
        } finally {
            $worker->close();
        }
    }

    private function challenge(): Challenge
    {
        $connection = $this->database->connection();
        $id         = $connection->fetchOne('SELECT uuid FROM identity_challenge');
        self::assertIsString($id);
        $store    = $this->store();
        $existing = $store->transaction(static fn () => $store->challenge($id));

        return new Challenge(IdentityId::create(), $existing->snapshot());
    }

    private function store(): IdentityStore
    {
        $container = $this->identity->container();

        return $container->get(IdentityStore::class);
    }
}
