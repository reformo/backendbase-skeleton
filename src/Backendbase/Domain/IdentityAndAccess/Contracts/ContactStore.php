<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts;

use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactInfo;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactValue;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationContextToken;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationScope;

interface ContactStore
{
    /**
     * @param callable(): TResult $work
     *
     * @return TResult
     *
     * @template TResult
     */
    public function transaction(callable $work): mixed;

    public function get(string $id): ContactInfo;

    public function slot(VerificationContextToken $token, VerificationScope $scope, ContactValue $contact): ContactInfo|null;

    public function save(ContactInfo $contact): void;
}
