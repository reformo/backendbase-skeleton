<?php

declare(strict_types=1);

namespace Tests\Support\Services;

use Backendbase\Domain\IdentityAndAccess\Application\Workflows\RegistrationWorkflow;
use Backendbase\Domain\IdentityAndAccess\Contracts\ContactStore;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\RegistrationInput;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\IdentityConflict;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactVerificationExpired;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\ObservedContactStore;
use DI\Container;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Assert;
use RuntimeException;

use function fgets;
use function fwrite;
use function trim;

use const STDIN;
use const STDOUT;

final class ContactConcurrencyWork
{
    /** @param list<string> $values */
    public static function run(Connection $connection, string $operation, array $values): void
    {
        $fixture = IdentityServiceFixture::create($connection);
        IdentityScenario::advance($fixture, 'PT61S');
        $container = $fixture->container();
        Assert::assertInstanceOf(Container::class, $container);
        $store = $container->get(ContactStore::class);
        $reads = 0;
        $container->set(ContactStore::class, new ObservedContactStore($store, static function () use ($fixture, $operation, &$reads): void {
            $reads++;
            if ($operation !== 'register-contact-expired' || $reads !== 2) {
                return;
            }

            IdentityScenario::advance($fixture, 'PT12H');
        }));
        $workflow = $container->get(RegistrationWorkflow::class);
        if (trim((string) fgets(STDIN)) !== 'begin') {
            throw new RuntimeException('The contact worker needs a begin command.');
        }

        try {
            $workflow->register(IdentityScenario::id(), new RegistrationInput($values[0], $values[1], 'valid-password-123', $values[2]));
            fwrite(STDOUT, "registered\n");
        } catch (IdentityConflict) {
            fwrite(STDOUT, "consumed\n");
        } catch (ContactVerificationExpired) {
            fwrite(STDOUT, "expired\n");
        }
    }
}
