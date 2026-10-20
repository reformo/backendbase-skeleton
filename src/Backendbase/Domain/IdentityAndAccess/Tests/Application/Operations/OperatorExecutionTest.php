<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Application\Operations;

use Backendbase\Domain\IdentityAndAccess\Application\IdentityOperation;
use Backendbase\Domain\IdentityAndAccess\Contracts\Command\Operations\ApplyOperatorAction;
use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityOperationResults;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\OperatorInput;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\IdentityConflict;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use Backendbase\Shared\Time\Clock;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class OperatorExecutionTest extends TestCase
{
    public function testPreviewAndApplyUseTheProductionDispatchAndClock(): void
    {
        $fixture    = IdentityFixture::create();
        $registered = IdentityScenario::registered($fixture);
        $container  = $fixture->container();
        $operation  = $container->get(IdentityOperation::class);
        $connection = $fixture->connection();
        $input      = new OperatorInput('invite-first-admin', $registered['accountId'], 'operator', 'ticket:invite', '');

        $preview = $operation->execute(new ApplyOperatorAction(IdentityScenario::id(), $input, false));
        self::assertSame(200, $preview->status());
        self::assertSame(0, $connection->fetchOne('SELECT COUNT(*) FROM admin'));

        IdentityScenario::advance($fixture, 'PT1H');
        $clock   = $container->get(Clock::class);
        $now     = $clock->now();
        $applied = $operation->execute(new ApplyOperatorAction(IdentityScenario::id(), $input, true));
        $data    = $applied->data();
        self::assertSame(200, $applied->status());
        self::assertSame($connection->fetchOne('SELECT uuid FROM admin'), $data['targetId']);
        self::assertEquals($now, new DateTimeImmutable($connection->fetchOne('SELECT created_at FROM admin')));
        self::assertJsonStringEqualsJsonString('{"approved":false,"invitation":"ticket:invite","approval":null}', $connection->fetchOne('SELECT state FROM admin'));
    }

    public function testRejectedActionDoesNotPublishAResult(): void
    {
        $results = $this->createMock(IdentityOperationResults::class);
        $results->expects(self::never())->method('put');
        $results->expects(self::never())->method('get');
        $fixture    = IdentityFixture::create([IdentityOperationResults::class => $results]);
        $registered = IdentityScenario::admin($fixture);
        $container  = $fixture->container();
        $operation  = $container->get(IdentityOperation::class);
        $input      = new OperatorInput('invite-first-admin', $registered['accountId'], 'operator', 'ticket:invite', '');
        $this->expectException(IdentityConflict::class);
        $operation->execute(new ApplyOperatorAction(IdentityScenario::id(), $input, true));
    }
}
