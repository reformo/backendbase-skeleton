<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Application;

use Backendbase\Domain\IdentityAndAccess\Adapters\Console\IdentityOperator;
use Backendbase\Domain\IdentityAndAccess\Adapters\Console\LocalOperatorAuthority;
use Backendbase\Domain\IdentityAndAccess\Application\IdentityOperation;
use Backendbase\Domain\IdentityAndAccess\Application\Operations\OperatorWorkflow;
use Backendbase\Domain\IdentityAndAccess\Contracts\AuthenticationSecrets;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\OperatorInput;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\IdentityConflict;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use Backendbase\Shared\Exception\ResourceAccessForbidden;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\CommandTester;

final class OperatorBoundaryTest extends TestCase
{
    public function testOnlyTheLocalRootCliCanApplyAnAction(): void
    {
        LocalOperatorAuthority::assertRoot('cli', 0);
        $this->expectException(ResourceAccessForbidden::class);
        LocalOperatorAuthority::assertRoot('cli', 1000);
    }

    public function testAnHttpProcessCannotUseRootAuthority(): void
    {
        $this->expectException(ResourceAccessForbidden::class);
        LocalOperatorAuthority::assertRoot('fpm-fcgi', 0);
    }

    #[DataProvider('invalidRequests')]
    public function testInvalidEvidenceAndActionsAreRejected(string $action, string $operator, string $invitation, string $approval): void
    {
        $this->expectException(InvalidArgumentException::class);
        new OperatorInput($action, IdentityScenario::id(), $operator, $invitation, $approval, 'valid.admin');
    }

    /** @return list<array{string, string, string, string}> */
    public static function invalidRequests(): array
    {
        return [
            ['unsupported', 'operator', 'invite', 'approve'],
            ['recover-totp', ' ', 'invite', 'approve'],
            ['recover-totp', 'operator', '', 'approve'],
            ['recover-totp', 'operator', 'invite', ''],
            ['recover-totp', 'operator', 'same', 'same'],
        ];
    }

    public function testOperatorPreviewAndInvalidCliOptionsDoNotWriteRecords(): void
    {
        $fixture   = IdentityFixture::create();
        $enrolled  = IdentityScenario::enrolled($fixture);
        $container = $fixture->container();
        $command   = new IdentityOperator($container->get(IdentityOperation::class));
        $tester    = new CommandTester($command);
        self::assertSame(Command::SUCCESS, $tester->execute(['--action' => 'recover-totp', '--target-id' => $enrolled['accountId'], '--operator' => 'operator', '--invitation' => 'request:123', '--approval' => 'approval:456']));
        self::assertStringContainsString('No identity record changed', $tester->getDisplay());
        self::assertSame(0, $fixture->connection()->fetchOne('SELECT COUNT(*) FROM identity_operator_action'));
        self::assertSame(Command::INVALID, $tester->execute([]));
        self::assertSame(Command::INVALID, $tester->execute(['--apply' => true]));
        $input = $this->createStub(InputInterface::class);
        $input->method('getOption')->willReturnMap([['apply', false], ['action', 123]]);
        self::assertSame(Command::INVALID, $command->run($input, new BufferedOutput()));
    }

    public function testAnUnrecordedAdminCannotUseFirstAdminApproval(): void
    {
        $fixture   = IdentityFixture::create();
        $admin     = IdentityScenario::admin($fixture);
        $container = $fixture->container();
        $workflow  = $container->get(OperatorWorkflow::class);
        $this->expectException(IdentityConflict::class);
        $workflow->run(new OperatorInput('approve-first-admin', $admin['actorId'], 'operator', '', 'ticket:approve'), $container->get(AuthenticationSecrets::class), new DateTimeImmutable(), true);
    }

    public function testFirstAdminApprovalCannotRunTwice(): void
    {
        $fixture   = IdentityFixture::create();
        $enrolled  = IdentityScenario::enrolled($fixture);
        $container = $fixture->container();
        $workflow  = $container->get(OperatorWorkflow::class);
        $secrets   = $container->get(AuthenticationSecrets::class);
        $now       = new DateTimeImmutable();
        $actor     = $workflow->run(new OperatorInput('invite-first-admin', $enrolled['accountId'], 'operator', 'ticket:invite', '', 'first.admin'), $secrets, $now, true);
        $approval  = new OperatorInput('approve-first-admin', $actor, 'operator', '', 'ticket:approve');
        $workflow->run($approval, $secrets, $now, true);
        $this->expectException(IdentityConflict::class);
        $workflow->run($approval, $secrets, $now, true);
    }
}
