<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Inbound\IdentityApi\Http;

use Backendbase\Domain\IdentityAndAccess\Contracts\Command\Administration;
use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityCommand;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\Administration as Input;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use Backendbase\Infrastructure\Adapters\Http\Input\RequestObjectInput;
use Backendbase\Infrastructure\Adapters\Mapping\ObjectMapper;
use Backendbase\Shared\Exception\InvalidUserInput;
use Backendbase\Shared\Time\Clock;
use Psr\Http\Message\ServerRequestInterface;

use function explode;
use function strtolower;

final readonly class AdministrationHttpInput
{
    public function __construct(private ObjectMapper $mapper, private Clock $clock)
    {
    }

    public function command(string $id, string $action, ServerRequestInterface $request): IdentityCommand
    {
        $principal = $request->getAttribute('identityPrincipal');
        if (! $principal instanceof Principal) {
            throw AuthenticationDenied::create('Authentication is not permitted.');
        }

        if (strtolower(explode(';', $request->getHeaderLine('Content-Type'), 2)[0]) !== 'application/json') {
            throw InvalidUserInput::create('The request body must use application/json.');
        }

        [$class, $fields] = match ($action) {
            'invite-admin' => [Input\AdminInvitationInput::class, ['accountId', 'loginName', 'evidence']],
            'approve-admin' => [Input\AdminApprovalInput::class, ['actorId', 'evidence']],
            'change-actor' => [Input\ActorChangeInput::class, ['actorId', 'realm', 'action']],
            default => throw InvalidUserInput::create('The administration operation is invalid.'),
        };
        $payload = RequestObjectInput::fromRequest($request, $fields);
        $mapper  = $this->mapper;
        $input   = $mapper->map($class, $payload);
        $clock   = $this->clock;

        return match (true) {
            $input instanceof Input\AdminInvitationInput => new Administration\InviteAdmin($id, new Input\AuthenticatedInput($principal, $input), $clock->now()),
            $input instanceof Input\AdminApprovalInput => new Administration\ApproveAdmin($id, new Input\AuthenticatedInput($principal, $input)),
            $input instanceof Input\ActorChangeInput => new Administration\ChangeActor($id, new Input\AuthenticatedInput($principal, $input), $clock->now()),
        };
    }
}
