<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Notification;

use Backendbase\Domain\IdentityAndAccess\Contracts\ContactNotifier;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactValue;
use Backendbase\Infrastructure\Configuration\IdentitySettings;
use Backendbase\Shared\Integrations\ReliableNotificationDelivery;
use Backendbase\Shared\Primitives\Notification\Address;
use Backendbase\Shared\Primitives\Notification\EmailNotification;
use Backendbase\Shared\Primitives\Notification\SmsNotification;

final readonly class ContactNotification implements ContactNotifier
{
    public function __construct(private ReliableNotificationDelivery $delivery, private IdentitySettings $settings)
    {
    }

    public function send(string $generationId, ContactValue $contact, string $code): void
    {
        $delivery = $this->delivery;
        $message  = 'Your contact verification code is ' . $code . '. The code expires after five minutes.';
        if ($contact->type() === 'mobile') {
            $notification = new SmsNotification($contact->value(), $message);
            $delivery->send('identity-contact-verification', $generationId, $notification);

            return;
        }

        $settings = $this->settings;
        $sender   = $settings->contactEmailSender();
        $email    = new EmailNotification();
        $email->setFromAddress(Address::create($sender, 'Backendbase'));
        $email->addToAddress(Address::create($contact->value()));
        $email->setSubject('Verify contact ownership');
        $email->setHtmlBody('<p>' . $message . '</p>');
        $delivery->send('identity-contact-verification', $generationId, $email);
    }
}
