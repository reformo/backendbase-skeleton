<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\CommandHandlers\Contacts;

use Backendbase\Domain\IdentityAndAccess\Application\Contacts\ContactDelivery;
use Backendbase\Domain\IdentityAndAccess\Application\Contacts\ContactService;
use Backendbase\Domain\IdentityAndAccess\Contracts\ContactStore;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\Contacts\ContactAttempt;
use Backendbase\Domain\IdentityAndAccess\Contracts\ReadModel\ContactInfoView;

final readonly class ContactExecution
{
    public function __construct(private ContactService $service, private ContactDelivery $delivery)
    {
    }

    public function execute(ContactAttempt $input, ContactStore $store): ContactInfoView
    {
        $service          = $this->service;
        [$contact, $code] = $service->execute($input, $store);
        $delivery         = $this->delivery;
        $result           = $delivery->send($contact, $code);

        return ContactInfoView::fromContact($result);
    }
}
