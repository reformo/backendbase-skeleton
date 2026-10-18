<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Services;

use Backendbase\Domain\IdentityAndAccess\Application\Workflows\RegistrationWorkflow;
use Backendbase\Domain\IdentityAndAccess\Contracts\ContactStore;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\RegistrationInput;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\ContactScenario;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\ObservedContactStore;
use DI\Container;
use PHPUnit\Framework\TestCase;
use Tests\Support\Services\IdentityServiceFixture;
use Tests\Support\Services\IsolatedMySql;
use Tests\Support\Services\MySqlWorker;

use function getenv;

final class MySqlContactVerificationTest extends TestCase
{
    public function testTwoRegistrationsCannotConsumeTheSameEvidence(): void
    {
        $fixture = self::fixture();
        $worker  = null;
        try {
            $connection   = $fixture->connection();
            $identity     = IdentityServiceFixture::create($connection);
            $values       = ContactScenario::verified($identity);
            $worker       = MySqlWorker::start($connection, 'register-contact', [$values['email'], $values['mobile'], $values['verificationContextToken']]);
            $connectionId = (int) $worker->receive();
            $container    = $identity->container();
            self::assertInstanceOf(Container::class, $container);
            $store = $container->get(ContactStore::class);
            $reads = 0;
            $container->set(ContactStore::class, new ObservedContactStore($store, static function () use ($worker, $connection, $connectionId, &$reads): void {
                $reads++;
                if ($reads !== 2) {
                    return;
                }

                $worker->send('begin');
                $worker->awaitLockWait($connection, $connectionId);
            }));
            $workflow = $container->get(RegistrationWorkflow::class);
            $workflow->register(IdentityScenario::id(), new RegistrationInput(...$values));
            self::assertSame('consumed', $worker->receive());
            self::assertSame(1, $connection->fetchOne('SELECT COUNT(*) FROM account'));
            self::assertSame(2, $connection->fetchOne('SELECT COUNT(*) FROM contact_verification WHERE account_id IS NOT NULL'));
        } finally {
            $worker?->close();
            $fixture->destroy();
        }
    }

    public function testLockWaitCannotExtendTheRegistrationFreshnessWindow(): void
    {
        $fixture = self::fixture();
        $worker  = null;
        try {
            $connection   = $fixture->connection();
            $identity     = IdentityServiceFixture::create($connection);
            $values       = ContactScenario::verified($identity);
            $worker       = MySqlWorker::start($connection, 'register-contact-expired', [$values['email'], $values['mobile'], $values['verificationContextToken']]);
            $connectionId = (int) $worker->receive();
            $container    = $identity->container();
            $store        = $container->get(ContactStore::class);
            $emailId      = $connection->fetchOne("SELECT uuid FROM contact_verification WHERE contact_type = 'email'");
            self::assertIsString($emailId);
            $store->transaction(static function () use ($store, $emailId, $worker, $connection, $connectionId): void {
                $store->get($emailId);
                $worker->send('begin');
                $worker->awaitLockWait($connection, $connectionId);
            });
            self::assertSame('expired', $worker->receive());
            self::assertSame(0, $connection->fetchOne('SELECT COUNT(*) FROM account'));
            self::assertSame(0, $connection->fetchOne('SELECT COUNT(*) FROM contact_verification WHERE account_id IS NOT NULL'));
        } finally {
            $worker?->close();
            $fixture->destroy();
        }
    }

    private static function fixture(): IsolatedMySql
    {
        if (getenv('BACKENDBASE_SERVICE_TESTS') !== '1') {
            self::markTestSkipped('An explicitly prepared MySQL 8.4 target is required.');
        }

        return IsolatedMySql::create();
    }
}
