<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Services;

use Backendbase\Domain\IdentityAndAccess\Adapters\Authentication\RedisTokenRegistry;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\Services\RedisFailover\AuthenticationAssertions;
use Tests\Support\Services\RedisFailover\AuthenticationSessions;
use Tests\Support\Services\RedisFailover\FailoverTopology;

use function getenv;

final class RedisFailoverTest extends TestCase
{
    private FailoverTopology $topology;

    protected function setUp(): void
    {
        if (getenv('BACKENDBASE_REDIS_FAILOVER_TESTS') !== '1') {
            self::markTestSkipped('The isolated Redis Sentinel failover runner is required.');
        }

        $this->topology = FailoverTopology::create();
    }

    #[DataProvider('realms')]
    public function testPlannedSentinelPromotionPreservesSessionsRevocationsAndExpiry(Realm $realm): void
    {
        $nodes      = $this->topology->nodes();
        $connection = $nodes->connection('primary');
        $registry   = new RedisTokenRegistry($connection);
        $sessions   = AuthenticationSessions::issue($realm, $registry);
        $sessions->revoke($registry);
        $client = $nodes->connect('primary');
        self::assertSame(1, $connection->raw('WAIT', 1, 5000));
        self::assertSame('object', $connection->type($sessions->tokenKey()));
        $expiresAt = $client->rawCommand('EXPIRETIME', $sessions->tokenKey());
        $sessions->assertReplicatedState($registry);
        $this->topology->plannedPromotion();
        $newConnection = $nodes->connection('replica');
        $newRegistry   = new RedisTokenRegistry($newConnection);
        $sessions->assertReplicatedState($newRegistry);
        $newClient = $nodes->connect('replica');
        self::assertSame('object', $newConnection->type($sessions->tokenKey()));
        self::assertSame($expiresAt, $newClient->rawCommand('EXPIRETIME', $sessions->tokenKey()));
        $sessions->assertReadOnlyEndpoint($registry);
        $sessions->assertNewLogin($newRegistry);
    }

    #[DataProvider('realms')]
    public function testPrimaryCrashFailsClosedUntilTheApplicationEndpointIsRebound(Realm $realm): void
    {
        $nodes      = $this->topology->nodes();
        $connection = $nodes->connection('primary');
        $registry   = new RedisTokenRegistry($connection);
        $sessions   = AuthenticationSessions::issue($realm, $registry);
        $sessions->revoke($registry);
        self::assertSame(1, $connection->raw('WAIT', 1, 5000));
        $gate = $sessions->gate($registry);
        $this->topology->control('kill', 'primary');
        AuthenticationAssertions::unavailable(static fn () => $gate->verify($realm, $sessions->token()));
        $this->topology->awaitPromotion();
        AuthenticationAssertions::unavailable(static fn () => $gate->verify($realm, $sessions->token()));
        $newRegistry = new RedisTokenRegistry($nodes->connection('replica'));
        $sessions->assertReplicatedState($newRegistry);
        $sessions->assertNewLogin($newRegistry);
    }

    #[DataProvider('realms')]
    public function testLostRevocationsRequireControlledLogoutBeforeTrafficResumes(Realm $realm): void
    {
        $nodes      = $this->topology->nodes();
        $connection = $nodes->connection('primary');
        $registry   = new RedisTokenRegistry($connection);
        $sessions   = AuthenticationSessions::issue($realm, $registry);
        self::assertSame(1, $connection->raw('WAIT', 1, 5000));
        $this->topology->disconnectReplication();
        $sessions->revoke($registry);
        self::assertSame(0, $connection->raw('WAIT', 1, 100));
        $sessions->assertReplicatedState($registry);
        $this->topology->control('kill', 'primary');
        $this->topology->awaitPromotion();
        $newRegistry = new RedisTokenRegistry($nodes->connection('replica'));
        $gate        = $sessions->gate($newRegistry);
        $verified    = $gate->verify($realm, $sessions->token('revoked'));
        $revoked     = $sessions->principal('revoked');
        self::assertSame($revoked->claims(), $verified->claims(), 'The stale replica has lost the acknowledged token revocation.');
        $verifiedLogout = $gate->verify($realm, $sessions->token('loggedOut'));
        $loggedOut      = $sessions->principal('loggedOut');
        self::assertSame($loggedOut->claims(), $verifiedLogout->claims(), 'The stale replica has lost the acknowledged logout.');
        $principal = $sessions->principal();
        $identity  = $principal->identity();
        $block     = 'identity:{' . ($realm === Realm::USER ? 'ADMIN' : 'USER') . ':' . $identity['accountId'] . '}:blocked';
        $promoted  = $nodes->connect('replica');
        self::assertTrue($promoted->set($block, '1', ['ex' => 900]));
        $this->topology->invalidateAccount($identity['accountId']);
        self::assertSame('1', $promoted->get($block));
        $sessions->assertAllDenied($newRegistry);
        $sessions->assertNewLogin($newRegistry);
    }

    #[DataProvider('realms')]
    public function testLostSentinelMajorityPreventsPromotionUntilQuorumReturns(Realm $realm): void
    {
        $nodes      = $this->topology->nodes();
        $connection = $nodes->connection('primary');
        $registry   = new RedisTokenRegistry($connection);
        $sessions   = AuthenticationSessions::issue($realm, $registry);
        $sessions->revoke($registry);
        self::assertSame(1, $connection->raw('WAIT', 1, 5000));
        $this->topology->control('kill', 'sentinel-two', 'sentinel-three');
        $this->topology->awaitNoQuorum();
        $this->topology->control('kill', 'primary');
        $gate = $sessions->gate($registry);
        AuthenticationAssertions::unavailable(static fn () => $gate->verify($realm, $sessions->token()));
        $this->topology->assertNoPromotion();
        $this->topology->control('up', '--detach', '--no-deps', 'sentinel-two', 'sentinel-three');
        AuthenticationAssertions::unavailable(static fn () => $gate->verify($realm, $sessions->token()));
        $this->topology->awaitPromotion();
        $newRegistry = new RedisTokenRegistry($nodes->connection('replica'));
        $sessions->assertReplicatedState($newRegistry);
    }

    /** @return iterable<string, array{Realm}> */
    public static function realms(): iterable
    {
        yield 'USER' => [Realm::USER];
        yield 'ADMIN' => [Realm::ADMIN];
    }
}
