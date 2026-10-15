<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Contacts;

use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\DoctrineIdentityTransaction;
use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\Entity\ContactInfo as Record;
use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\IdentitySession;
use Backendbase\Domain\IdentityAndAccess\Contracts\ContactStore;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactInfo;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactValue;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationContextToken;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationScope;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Ramsey\Uuid\Uuid;

final readonly class DoctrineContactStore implements ContactStore
{
    public function __construct(private IdentitySession $session, private DoctrineIdentityTransaction $transaction)
    {
    }

    public function transaction(callable $work): mixed
    {
        $transaction = $this->transaction;

        return $transaction->run($work);
    }

    public function get(string $id): ContactInfo
    {
        $session   = $this->session;
        $manager   = $session->manager();
        $reference = $manager->find(Record::class, Uuid::fromString($id));
        if ($reference !== null) {
            $owner = new ContactOwnership($session);
            $owner->assertAllowed($reference->toDomain());
        }

        $record = $this->record($id);
        if ($record === null) {
            throw AuthenticationDenied::create('Contact verification is not permitted.');
        }

        return $record->toDomain();
    }

    public function slot(VerificationContextToken $token, VerificationScope $scope, ContactValue $contact): ContactInfo|null
    {
        $session    = $this->session;
        $manager    = $session->manager();
        $repository = $manager->getRepository(Record::class);
        $record     = $repository->findOneBy(['contextHash' => $token->digest(), 'realm' => $scope->realm(), 'purpose' => $scope->purpose(), 'contactType' => $contact->type()]);
        if ($record === null) {
            return null;
        }

        return $this->get($record->id());
    }

    public function save(ContactInfo $contact): void
    {
        $session = $this->session;
        $owner   = new ContactOwnership($session);
        $owner->assertAllowed($contact);
        $manager = $session->manager();
        $unit    = $manager->getUnitOfWork();
        $id      = Uuid::fromString($contact->id());
        $managed = $unit->tryGetById(['id' => $id], Record::class);
        if ($managed instanceof Record) {
            $managed->synchronize($contact);

            return;
        }

        $manager->persist(Record::create($contact));
    }

    private function record(string $id): Record|null
    {
        $session    = $this->session;
        $manager    = $session->manager();
        $connection = $manager->getConnection();
        $lock       = $connection->getDatabasePlatform() instanceof SQLitePlatform ? null : LockMode::PESSIMISTIC_WRITE;
        $record     = $manager->find(Record::class, Uuid::fromString($id), $lock);
        if ($record !== null) {
            $manager->refresh($record, $lock);
        }

        return $record;
    }
}
