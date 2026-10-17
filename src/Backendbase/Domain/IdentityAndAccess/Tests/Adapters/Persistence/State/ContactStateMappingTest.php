<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Adapters\Persistence\State;

use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\Entity\ContactVerification;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationUnavailable;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactInfo as Contact;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Domain\IdentityAndAccess\Domain\Primitives\ContactState;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class ContactStateMappingTest extends TestCase
{
    public function testMappedColumnsRemainAuthoritativeOverJsonCopies(): void
    {
        $fixture   = IdentityFixture::create();
        $container = $fixture->container();
        $manager   = $container->get(EntityManagerInterface::class);
        $record    = ContactVerification::create(new Contact(IdentityId::create(), StateExamples::CONTACT));
        $copy      = [...StateExamples::CONTACT, 'realm' => 'ADMIN', 'purpose' => 'admin-contact', 'contactValue' => 'other@example.com', 'contextHash' => 'different', 'accountId' => 'old-account', 'actorId' => 'old-actor', 'verifiedAt' => '2026-10-17 10:01:00.000000', 'revision' => 99];
        $record->replaceState(ContactState::fromArray($copy));
        $manager->persist($record);
        $manager->flush();
        $id = $record->id();
        $manager->clear();
        $loaded = $manager->find(ContactVerification::class, $id);
        self::assertNotNull($loaded);
        $state = $loaded->state();
        self::assertSame($copy, $state->toArray());
        $contact = $loaded->toDomain();
        self::assertEquals(StateExamples::CONTACT, $contact->snapshot());
    }

    public function testInvalidStateDoesNotPartiallyChangeTheRecord(): void
    {
        $id      = IdentityId::create();
        $record  = ContactVerification::create(new Contact($id, StateExamples::CONTACT));
        $invalid = new Contact($id, [...StateExamples::CONTACT, 'contactValue' => 'other@example.com', 'futureField' => true]);
        try {
            $record->synchronize($invalid);
            self::fail('Unsupported state must fail before record mutation.');
        } catch (AuthenticationUnavailable) {
            $contact = $record->toDomain();
            self::assertEquals(StateExamples::CONTACT, $contact->snapshot());
        }
    }
}
