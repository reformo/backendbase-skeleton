<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\CommandHandlers\Contacts;

use Backendbase\Domain\IdentityAndAccess\Application\Contacts\ContactDelivery;
use Backendbase\Domain\IdentityAndAccess\Application\Contacts\ContactService;
use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityOperationResults;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\Contacts\ContactAttempt;
use Backendbase\Domain\IdentityAndAccess\Contracts\ReadModel\ContactInfoView;
use Backendbase\Domain\IdentityAndAccess\Contracts\ReadModel\IdentityOperationResult;

final readonly class ContactCompletion
{
    public function __construct(private ContactDelivery $delivery, private IdentityOperationResults $results)
    {
    }

    public function complete(string $operationId, ContactAttempt $input, ContactService $service): void
    {
        $delivery = $this->delivery;
        $contact  = $delivery->execute($input, $service);
        $view     = ContactInfoView::fromContact($contact);
        $data     = $view->jsonSerialize();
        $results  = $this->results;
        $results->put($operationId, new IdentityOperationResult(200, $data));
    }
}
