<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Http\Identity;

use Backendbase\Domain\IdentityAndAccess\Contracts\Input\Contacts\ContactAttempt;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\LoginInput;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactValue;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationClient;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationCode;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationContextToken;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Infrastructure\Adapters\Http\Input\RequestObjectInput;
use Backendbase\Shared\Exception\InvalidUserInput;
use Psr\Http\Message\ServerRequestInterface;

use function explode;
use function is_string;
use function strtolower;

final class ContactHttpInput
{
    /** @param array<string, string> $arguments */
    public function read(ServerRequestInterface $request, array $arguments): ContactAttempt
    {
        $action = $request->getAttribute('contactAction');
        $realm  = $request->getAttribute('expectedRealm');
        if (! is_string($action) || ! $realm instanceof Realm) {
            throw AuthenticationDenied::create('A trusted contact verification policy is required.');
        }

        if (strtolower(explode(';', $request->getHeaderLine('Content-Type'), 2)[0]) !== 'application/json') {
            throw InvalidUserInput::create('The request body must use application/json.');
        }

        $authenticated = $request->getAttribute('contactAuthenticated') === true;
        $principal     = $authenticated ? $request->getAttribute('identityPrincipal') : null;
        if ($authenticated && ! $principal instanceof Principal) {
            throw AuthenticationDenied::create('Contact verification is not permitted.');
        }

        $fields  = $this->fields($action, $realm, $authenticated);
        $payload = RequestObjectInput::fromRequest($request, $fields);
        $server  = $request->getServerParams();
        $client  = new VerificationClient(self::text($server, 'REMOTE_ADDR'));
        $token   = new VerificationContextToken(self::text($payload, 'verificationContextToken'));
        $contact = $action === 'request' && $realm === Realm::USER && ! $authenticated ? new ContactValue(self::text($payload, 'contactType'), self::text($payload, 'contactValue')) : null;
        $login   = $action === 'request' && $realm === Realm::ADMIN && ! $authenticated ? new LoginInput(self::text($payload, 'identifier'), self::text($payload, 'password')) : null;

        return new ContactAttempt(['action' => $action, 'realm' => $realm, 'contactId' => $arguments['contact-info-id'] ?? null, 'token' => $token, 'client' => $client, 'contact' => $contact, 'contactType' => $action === 'request' ? self::text($payload, 'contactType') : null, 'code' => $action === 'confirm' ? new VerificationCode(self::text($payload, 'code')) : null, 'principal' => $principal, 'login' => $login]);
    }

    /** @return list<string> */
    private function fields(string $action, Realm $realm, bool $authenticated): array
    {
        if ($action === 'confirm') {
            return ['verificationContextToken', 'code'];
        }

        if ($action === 'resend') {
            return ['verificationContextToken'];
        }

        if ($realm === Realm::USER && ! $authenticated) {
            return ['contactType', 'contactValue', 'verificationContextToken'];
        }

        return $authenticated ? ['contactType', 'verificationContextToken'] : ['identifier', 'password', 'contactType', 'verificationContextToken'];
    }

    /** @param array<array-key, mixed> $payload */
    private static function text(array $payload, string $field): string
    {
        $value = $payload[$field] ?? null;
        if (! is_string($value)) {
            throw InvalidUserInput::create('The required field must be a string.');
        }

        return $value;
    }
}
