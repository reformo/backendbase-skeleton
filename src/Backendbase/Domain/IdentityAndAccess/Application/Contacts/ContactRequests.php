<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\Contacts;

use Backendbase\Domain\IdentityAndAccess\Contracts\ContactStore;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\IdentityConflict;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactInfo;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactValue;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationClient;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationCode;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationContextToken;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationScope;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;

final readonly class ContactRequests
{
    public function __construct(private ContactStore $store, private ContactIssuance $issuance)
    {
    }

    /** @return array{ContactInfo, VerificationCode|null} */
    public function request(ContactValue $value, VerificationContextToken $token, VerificationScope $scope, VerificationClient $client): array
    {
        $store    = $this->store;
        $existing = $store->transaction(fn () => $this->existing($value, $token, $scope));
        if ($existing !== null) {
            return [$existing, null];
        }

        $issuance = $this->issuance;
        $issuance->reserve($value, $client);
        try {
            return $store->transaction(fn () => $this->create($value, $token, $scope));
        } catch (IdentityConflict $exception) {
            $existing = $store->transaction(fn () => $this->existing($value, $token, $scope));
            if ($existing === null) {
                throw $exception;
            }

            return [$existing, null];
        }
    }

    /** @return array{ContactInfo, VerificationCode|null} */
    private function create(ContactValue $value, VerificationContextToken $token, VerificationScope $scope): array
    {
        $existing = $this->existing($value, $token, $scope);
        if ($existing !== null) {
            return [$existing, null];
        }

        $store    = $this->store;
        $issuance = $this->issuance;
        $now      = $issuance->now();
        $contact  = ContactInfo::request(IdentityId::create(), $value, $token, $scope, $now);
        $code     = VerificationCode::generate();
        $issuance->issue($contact, $code, $now);
        $store->save($contact);

        return [$contact, $code];
    }

    /** @return array{ContactInfo, VerificationCode} */
    public function resend(string $id, VerificationContextToken $token, VerificationScope $scope, VerificationClient $client): array
    {
        $store    = $this->store;
        $contact  = $store->transaction(fn () => $this->resendable($id, $token, $scope));
        $issuance = $this->issuance;
        $issuance->reserve($contact->contact(), $client);

        return $store->transaction(function () use ($id, $token, $scope): array {
            $contact  = $this->resendable($id, $token, $scope);
            $issuance = $this->issuance;
            $store    = $this->store;
            $code     = $issuance->replacement($contact);
            $now      = $issuance->now();
            $issuance->issue($contact, $code, $now);
            $store->save($contact);

            return [$contact, $code];
        });
    }

    private function resendable(string $id, VerificationContextToken $token, VerificationScope $scope): ContactInfo
    {
        $store   = $this->store;
        $contact = $store->get($id);
        $contact->assertContext($token, $scope);
        $issuance = $this->issuance;
        $contact->assertResendAllowed($issuance->now());

        return $contact;
    }

    private function existing(ContactValue $value, VerificationContextToken $token, VerificationScope $scope): ContactInfo|null
    {
        $store   = $this->store;
        $contact = $store->slot($token, $scope, $value);
        if ($contact === null) {
            return null;
        }

        $contact->assertContext($token, $scope);
        $stored = $contact->contact();
        if ($stored->value() !== $value->value()) {
            throw IdentityConflict::create('The contact value in this verification context cannot change.');
        }

        return $contact;
    }
}
