<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Application;

use Backendbase\Domain\IdentityAndAccess\Application\Contacts\ContactIssuance;
use Backendbase\Domain\IdentityAndAccess\Application\Contacts\ContactRequests;
use Backendbase\Domain\IdentityAndAccess\Contracts\ContactStore;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\IdentityConflict;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactInfo;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactValue;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationClient;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationContextToken;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationScope;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\ContactScenario;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function count;

final class ContactRequestRaceTest extends TestCase
{
    #[DataProvider('races')]
    public function testConcurrentCreatesReturnOnlyTheWinningContact(string $race): void
    {
        $fixture   = IdentityFixture::create();
        $container = $fixture->container();
        $value     = new ContactValue('email', 'ada@example.com');
        $token     = new VerificationContextToken(ContactScenario::token());
        $scope     = new VerificationScope(Realm::USER);
        $winner    = ContactInfo::request(IdentityId::create(), $value, $token, $scope, new DateTimeImmutable('2026-10-14T10:00:00Z'));
        $store     = $this->createMock(ContactStore::class);
        $store->method('transaction')->willReturnCallback(static fn (callable $work): mixed => $work());
        $results = match ($race) {
            'before insert' => [null, $winner],
            'unique collision' => [null, null, $winner],
            default => [null, null, null],
        };
        $store->expects(self::exactly(count($results)))->method('slot')->willReturnOnConsecutiveCalls(...$results);
        if ($race !== 'before insert') {
            $store->expects(self::once())->method('save')->willThrowException(IdentityConflict::create('Concurrent insertion.'));
        }

        if ($race === 'unrelated conflict') {
            $this->expectException(IdentityConflict::class);
        }

        $requests         = new ContactRequests($store, $container->get(ContactIssuance::class));
        [$contact, $code] = $requests->request($value, $token, $scope, new VerificationClient('127.0.0.1'));
        self::assertSame($winner->id(), $contact->id());
        self::assertNull($code);
    }

    /** @return iterable<string, array{string}> */
    public static function races(): iterable
    {
        foreach (['before insert', 'unique collision', 'unrelated conflict'] as $race) {
            yield $race => [$race];
        }
    }
}
