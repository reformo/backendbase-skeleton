<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\CommandHandlers\Contacts;

use Backendbase\Domain\IdentityAndAccess\Contracts\ContactStore;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\Contacts\ContactAttempt;
use Backendbase\Domain\IdentityAndAccess\Contracts\ReadModel\ContactInfoView;

final readonly class ContactExecutionServices
{
    public function __construct(private ContactExecution $execution, private ContactStore $store)
    {
    }

    public function execute(ContactAttempt $input): ContactInfoView
    {
        $execution = $this->execution;

        return $execution->execute($input, $this->store);
    }
}
