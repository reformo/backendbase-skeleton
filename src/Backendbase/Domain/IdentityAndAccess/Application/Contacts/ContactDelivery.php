<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\Contacts;

use Backendbase\Domain\IdentityAndAccess\Contracts\ContactNotifier;
use Backendbase\Domain\IdentityAndAccess\Contracts\ContactStore;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\Contacts\ContactAttempt;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationUnavailable;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactInfo;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactVerificationPolicy;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationCode;
use Throwable;

final readonly class ContactDelivery
{
    private const string ACCEPTED = 'accepted';
    private const string UNKNOWN  = 'unknown';

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

        $state        = $contact->snapshot();
        $generationId = $state->generationId;
        $notifier     = $this->notifier;
        try {
            $destination  = $contact->contact();
            $deliveryCode = $code->forDelivery();
            $notifier->send($generationId, $destination, $deliveryCode);
        } catch (Throwable $exception) {
            $contactId = $contact->id();
            $this->recordGenerationResult($contactId, $generationId, self::UNKNOWN);

            throw AuthenticationUnavailable::create('Contact verification delivery is unavailable.', ['contactInfoId' => $contactId, 'retryAfter' => ContactVerificationPolicy::RESEND_RETRY_SECONDS], previous: $exception);
        }

        $contactId = $contact->id();

        return $this->recordGenerationResult($contactId, $generationId, self::ACCEPTED);
    }

    private function recordGenerationResult(string $id, string $generationId, string $status): ContactInfo
    {
        $store = $this->store;

        return $store->transaction(static function () use ($store, $id, $generationId, $status): ContactInfo {
            $contact = $store->get($id);
            $contact->recordDelivery($generationId, $status);
            $store->save($contact);

            return $contact;
        });
    }
}
