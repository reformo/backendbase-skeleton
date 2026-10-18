<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Support;

use Backendbase\Domain\IdentityAndAccess\Contracts\ContactStore;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactInfo;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactValue;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationContextToken;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationScope;
use Closure;

final readonly class ObservedContactStore implements ContactStore
{
    /** @param Closure(): void $afterRead */
    public function __construct(private ContactStore $store, private Closure $afterRead)
    {
    }

    public function transaction(callable $work): mixed
    {
        $store = $this->store;

        return $store->transaction($work);
    }

    public function get(string $id): ContactInfo
    {
        $store   = $this->store;
        $contact = $store->get($id);
        $observe = $this->afterRead;
        $observe();

        return $contact;
    }

    public function slot(VerificationContextToken $token, VerificationScope $scope, ContactValue $contact): ContactInfo|null
    {
        $store = $this->store;
        $found = $store->slot($token, $scope, $contact);
        if ($found !== null) {
            $observe = $this->afterRead;
            $observe();
        }

        return $found;
    }

    public function save(ContactInfo $contact): void
    {
        $store = $this->store;
        $store->save($contact);
    }
}
