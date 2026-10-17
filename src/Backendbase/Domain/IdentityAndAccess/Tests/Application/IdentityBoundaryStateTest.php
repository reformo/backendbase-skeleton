<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Application;

use Backendbase\Domain\IdentityAndAccess\Application\AuthenticationGate;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class IdentityBoundaryStateTest extends TestCase
{
    public function testCurrentSessionRejectsPersistedActorAccountMismatch(): void
    {
        $fixture    = IdentityFixture::create();
        $first      = IdentityScenario::verified($fixture);
        $principal  = IdentityScenario::principal($fixture);
        $connection = $fixture->connection();
        $accountId  = Uuid::uuid7();
        $connection->executeStatement('UPDATE user SET account_id = :account WHERE uuid = :actor', ['account' => $accountId->getBytes(), 'actor' => $first['actorId']], ['account' => ParameterType::BINARY]);
        $container = $fixture->container();
        $gate      = $container->get(AuthenticationGate::class);
        $this->expectException(AuthenticationDenied::class);
        $gate->confirm($principal);
    }
}
