<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Notification;

use Backendbase\Domain\IdentityAndAccess\Contracts\OwnershipNotifier;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\AccountEmail;
use Backendbase\Infrastructure\Configuration\IdentitySettings;
use Backendbase\Shared\Integrations\ReliableNotificationDelivery;
use Backendbase\Shared\Primitives\Notification\Address;
use Backendbase\Shared\Primitives\Notification\EmailNotification;

use function htmlspecialchars;

use const ENT_QUOTES;

final readonly class OwnershipMailer implements OwnershipNotifier
{
    public function __construct(private ReliableNotificationDelivery $delivery, private IdentitySettings $settings)
    {
    }

    public function send(string $challengeId, AccountEmail $email, string $code): void
    {
        $currentDelivery = $this->delivery;

        $settings     = $this->settings;
        $notification = new EmailNotification();
        $notification->setFromAddress(Address::create($settings->ownershipSender(), 'Backendbase'));
        $notification->addToAddress(Address::create($email->toString()));
        $notification->setSubject('Verify account email ownership');
        $notification->setHtmlBody('<p>Your ownership verification code is <strong>' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '</strong>.</p><p>The code expires after five minutes.</p>');
        $currentDelivery->send('identity-contact-ownership', $challengeId, $notification);
    }
}
