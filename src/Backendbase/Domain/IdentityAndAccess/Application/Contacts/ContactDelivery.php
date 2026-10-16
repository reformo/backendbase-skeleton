<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\Contacts;

use Backendbase\Domain\IdentityAndAccess\Contracts\ContactNotifier;
use Backendbase\Domain\IdentityAndAccess\Contracts\ContactStore;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\Contacts\ContactAttempt;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationUnavailable;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactInfo;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationCode;
use Throwable;

final readonly class ContactDelivery
{
    public function __construct(private ContactStore $store, private ContactNotifier $notifier)
    {
    }

    public function execute(ContactAttempt $input, ContactService $service): ContactInfo
    {
        $store            = $this->store;
        [$contact, $code] = $service->execute($input, $store);

        return $this->send($contact, $code);
    }

    public function send(ContactInfo $contact, VerificationCode|null $code): ContactInfo
    {
        if ($code === null) {
            return $contact;
        }

        $state      = $contact->snapshot();
        $generation = $state['generationId'];
        $notifier   = $this->notifier;
        try {
            $notifier->send($generation, $contact->contact(), $code->forDelivery());
        } catch (Throwable $exception) {
            $this->record($contact->id(), $generation, 'unknown');

            throw AuthenticationUnavailable::create('Contact verification delivery is unavailable.', ['contactInfoId' => $contact->id(), 'retryAfter' => 60], previous: $exception);
        }

        return $this->record($contact->id(), $generation, 'accepted');
    }

    private function record(string $id, string $generation, string $status): ContactInfo
    {
        $store = $this->store;

        return $store->transaction(static function () use ($store, $id, $generation, $status): ContactInfo {
            $contact = $store->get($id);
            $contact->recordDelivery($generation, $status);
            $store->save($contact);

            return $contact;
        });
    }
}
