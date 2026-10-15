<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\Contacts;

use Backendbase\Domain\IdentityAndAccess\Contracts\ContactStore;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\Contacts\ContactAttempt;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactInfo;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationCode;

final readonly class ContactService
{
    public function __construct(private ContactAuthorizer $authorizer, private ContactOperations $operations)
    {
    }

    /** @return array{ContactInfo, VerificationCode|null} */
    public function execute(ContactAttempt $input, ContactStore $contacts): array
    {
        $authorizer        = $this->authorizer;
        [$scope, $contact] = $authorizer->resolve($input, $contacts);
        $operations        = $this->operations;

        return $operations->execute($input, $scope, $contact);
    }
}
