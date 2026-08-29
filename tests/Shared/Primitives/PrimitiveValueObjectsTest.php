<?php

declare(strict_types=1);

namespace Tests\Shared\Primitives;

use Backendbase\Shared\Exception\InvalidResourceId;
use Backendbase\Shared\Exception\InvalidUserInput;
use Backendbase\Shared\Primitives\Coordinates;
use Backendbase\Shared\Primitives\Email;
use Backendbase\Shared\Primitives\Exception\InvalidEmailAddress;
use Backendbase\Shared\Primitives\Exception\InvalidName;
use Backendbase\Shared\Primitives\Filter;
use Backendbase\Shared\Primitives\Identifier\CorporateTaxId;
use Backendbase\Shared\Primitives\Identifier\PrivateTaxId;
use Backendbase\Shared\Primitives\JsonSerializableArrayCollection;
use Backendbase\Shared\Primitives\Name;
use Backendbase\Shared\Primitives\PasswordHash;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SensitiveParameterValue;

use function strlen;

final class PrimitiveValueObjectsTest extends TestCase
{
    #[Test]
    public function itExposesSimpleValueObjects(): void
    {
        $coordinates = new Coordinates('29.0', '41.0');
        self::assertSame('29.0', $coordinates->longitude());
        self::assertSame('41.0', $coordinates->latitude());

        $filter = new Filter('needle', ['name', 'email'], null);
        self::assertSame('needle', $filter->query());
        self::assertSame(['name', 'email'], $filter->targetFields());
        self::assertSame([], $filter->criteria());

        $collection = new JsonSerializableArrayCollection(['first', 'second']);
        self::assertSame(['first', 'second'], $collection->jsonSerialize());
        self::assertCount(2, $collection);
        self::assertSame(['first', 'second'], [...$collection]);
    }

    #[Test]
    public function itValidatesEmailAndNameValues(): void
    {
        $email = new Email('user@example.com');
        self::assertSame('user@example.com', $email->toString());
        self::assertSame('user@example.com', (string) $email);
        self::assertSame('Mehmet', (string) new Name('Mehmet'));

        try {
            new Email('invalid');
            self::fail('An invalid email must fail.');
        } catch (InvalidEmailAddress $exception) {
            self::assertSame('Email provided is not a valid e-mail address', $exception->getMessage());
            self::assertSame([], $exception->context());
        }

        $this->expectException(InvalidName::class);
        new Name('M');
    }

    #[Test]
    public function itCreatesAndSerializesEntityIdentifiers(): void
    {
        $identifier = TestEntityId::create();
        self::assertSame($identifier->id(), $identifier->toString());
        self::assertSame($identifier->id(), (string) $identifier);
        self::assertSame($identifier->id(), $identifier->jsonSerialize());
        self::assertSame(
            '00000000-0000-0000-0000-000000000000',
            TestEntityId::null()->id(),
        );
        self::assertNotSame($identifier->id(), TestEntityId::generate()->id());
        self::assertSame($identifier->id(), TestEntityId::fromString($identifier->id())->id());

        $integerIdentifier = TestEntityIntId::fromValue(42);
        self::assertSame(42, $integerIdentifier->id());
        self::assertSame(42, $integerIdentifier->jsonSerialize());
        $privateIdentifier = TestEntityPrivateId::fromValue(7);
        self::assertSame(7, $privateIdentifier->id());
        self::assertSame(7, $privateIdentifier->jsonSerialize());

        $objectIdentifier = TestEntityObjectId::generate();
        self::assertSame($objectIdentifier->id(), (string) $objectIdentifier);
        self::assertSame(24, strlen($objectIdentifier->id()));
    }

    #[Test]
    public function itRejectsAnInvalidEntityIdentifier(): void
    {
        $this->expectException(InvalidResourceId::class);
        TestEntityId::fromString('invalid');
    }

    #[Test]
    public function itChangesAndVerifiesPasswordHashes(): void
    {
        PasswordHash::validatePassword(new SensitiveParameterValue('secret'));
        $initial = PasswordHash::create('not-a-password-hash');
        self::assertSame('not-a-password-hash', $initial->toString());
        self::assertFalse($initial->verifyHash(new SensitiveParameterValue('secret')));

        $changed = $initial->changeHashWithNewPassword(new SensitiveParameterValue('secret'));
        self::assertNotSame($initial->toString(), $changed->toString());
        self::assertTrue($changed->verifyHash(new SensitiveParameterValue('secret')));
    }

    #[Test]
    public function itValidatesTaxIdentifiers(): void
    {
        self::assertSame('1000000000', (new CorporateTaxId('1000000000'))->taxId());
        self::assertSame('10000000146', (new PrivateTaxId('10000000146'))->taxId());

        foreach (['123', '1000000001'] as $invalidCorporateTaxId) {
            try {
                new CorporateTaxId($invalidCorporateTaxId);
                self::fail('The corporate tax identifier must fail.');
            } catch (InvalidUserInput) {
                self::addToAssertionCount(1);
            }
        }

        foreach (['01234567890', '123', '10000000136', '10000000145'] as $invalidPrivateTaxId) {
            try {
                new PrivateTaxId($invalidPrivateTaxId);
                self::fail('The private tax identifier must fail.');
            } catch (InvalidUserInput) {
                self::addToAssertionCount(1);
            }
        }
    }
}
