<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Inbound\IdentityApi\Http;

use Backendbase\Domain\IdentityAndAccess\Contracts\Command\Authentication;
use Backendbase\Domain\IdentityAndAccess\Contracts\Command\Lifecycle;
use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityCommand;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Infrastructure\Adapters\Http\Input\RequestObjectInput;
use Backendbase\Infrastructure\Adapters\Mapping\ObjectMapper;
use Backendbase\Shared\Exception\InvalidUserInput;
use Backendbase\Shared\Time\Clock;
use Psr\Http\Message\ServerRequestInterface;

use function explode;
use function strtolower;

final readonly class IdentityHttpInput
{
    public function __construct(private ObjectMapper $mapper, private Clock $clock)
    {
    }

    public function command(string $id, string $action, Realm $realm, ServerRequestInterface $request): IdentityCommand
    {
        if ($action === 'logout' || $action === 'close-account' || $action === 'revoke-token') {
            RequestObjectInput::assertEmptyBody($request);
            $principal = $request->getAttribute('identityPrincipal');
            if (! $principal instanceof Principal) {
                throw AuthenticationDenied::create('Authentication is not permitted.');
            }

            $clock = $this->clock;

            return match ($action) {
                'logout' => new Lifecycle\Logout($id, $principal),
                'revoke-token' => new Lifecycle\RevokeAccessToken($id, $principal),
                default => new Lifecycle\CloseAccount($id, new Input\AccountClosure($principal, $clock->now())),
            };
        }

        $contentType = strtolower(explode(';', $request->getHeaderLine('Content-Type'), 2)[0]);
        if ($contentType !== 'application/json') {
            throw InvalidUserInput::create('The request body must use application/json.');
        }

        $input = $this->payload($action, $request);

        return match (true) {
            $input instanceof Input\RegistrationInput && $action === 'register' && $realm === Realm::USER => new Authentication\RegisterUser($id, $input),
            $input instanceof Input\LoginInput => $this->loginCommand($id, $action, new Input\RealmLogin($realm, $input)),
            $input instanceof Input\ProofInput => $this->proofCommand($id, $action, new Input\RealmProof($realm, $input)),
            default => throw InvalidUserInput::create('The authentication operation is invalid.'),
        };
    }

    private function payload(string $action, ServerRequestInterface $request): Input\RegistrationInput|Input\LoginInput|Input\ProofInput
    {
        [$class, $fields] = match ($action) {
            'register' => [Input\RegistrationInput::class, ['email', 'loginName', 'password']],
            'begin-login', 'prepare-mfa', 'resend-ownership' => [Input\LoginInput::class, ['loginName', 'password']],
            'verify-ownership', 'complete-mfa', 'complete-login' => [Input\ProofInput::class, ['challengeId', 'code']],
            default => throw InvalidUserInput::create('The authentication operation is invalid.'),
        };
        $payload = RequestObjectInput::fromRequest($request, $fields);
        $mapper  = $this->mapper;

        return $mapper->map($class, $payload);
    }

    private function loginCommand(string $id, string $action, Input\RealmLogin $input): IdentityCommand
    {
        return match ($action) {
            'begin-login' => new Authentication\BeginLogin($id, $input),
            'prepare-mfa' => new Authentication\PrepareTotpEnrollment($id, $input),
            'resend-ownership' => new Authentication\ResendOwnership($id, $input),
            default => throw InvalidUserInput::create('The authentication operation is invalid.'),
        };
    }

    private function proofCommand(string $id, string $action, Input\RealmProof $input): IdentityCommand
    {
        return match ($action) {
            'verify-ownership' => new Authentication\VerifyOwnership($id, $input),
            'complete-mfa' => new Authentication\CompleteTotpEnrollment($id, $input),
            'complete-login' => new Authentication\CompleteLogin($id, $input),
            default => throw InvalidUserInput::create('The authentication operation is invalid.'),
        };
    }
}
