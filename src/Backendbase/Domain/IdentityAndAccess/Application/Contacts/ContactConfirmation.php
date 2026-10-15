<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\Contacts;

use Backendbase\Domain\IdentityAndAccess\Contracts\ContactStore;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactInfo;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationCode;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationContextToken;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationScope;

final readonly class ContactConfirmation
{
    public function __construct(private ContactStore $store, private ContactChecks $checks)
    {
    }

    public function confirm(string $id, VerificationContextToken $token, VerificationScope $scope, VerificationCode $code): ContactInfo
    {
        $store   = $this->store;
        $contact = $store->transaction(fn () => $this->permitted($id, $token, $scope));
        $checks  = $this->checks;
        $checks->reserve($contact);
        [$contact, $valid] = $store->transaction(function () use ($id, $token, $scope, $code): array {
            $contact  = $this->permitted($id, $token, $scope);
            $checks   = $this->checks;
            $now      = $checks->now();
            $valid    = $checks->valid($contact, $code);
            $accepted = $contact->confirm($valid, $now);
            $store    = $this->store;
            $store->save($contact);

            return [$contact, $accepted];
        });
        if (! $valid) {
            throw AuthenticationDenied::create('Contact verification is not permitted.');
        }

        $checks->accept($contact);

        return $contact;
    }

    private function permitted(string $id, VerificationContextToken $token, VerificationScope $scope): ContactInfo
    {
        $store   = $this->store;
        $contact = $store->get($id);
        $contact->assertContext($token, $scope);
        $checks = $this->checks;
        $contact->assertConfirmationAllowed($checks->now());

        return $contact;
    }
}
