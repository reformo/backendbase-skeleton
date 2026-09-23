<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Account\Handlers;

use Backendbase\Domain\IdentityAndAccess\Contracts\Command\RegisterAccount as RegisterAccountCommand;
use Backendbase\Domain\IdentityAndAccess\Domain\AccountId;
use Backendbase\Infrastructure\Adapters\Http\Actions\Action;
use Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Account\AccountRequestInput;
use Backendbase\Shared\Authorization\AccessControl;
use Backendbase\Shared\CQRS\CommandBus;
use Laminas\Diactoros\Response\JsonResponse;
use Override;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

final class RegisterAccount extends Action
{
    public function __construct(private readonly CommandBus $commandBus, LoggerInterface $logger)
    {
        parent::__construct($logger);
    }

    #[Override]
    protected function action(): Response
    {
        $payload   = AccountRequestInput::registrationPayload($this->request->getParsedBody());
        $accountId = AccountId::generate();
        $this->commandBus->handle(new RegisterAccountCommand(
            $accountId,
            AccountRequestInput::email($payload['email']),
            AccountRequestInput::passwordHash($payload['password']),
            AccountRequestInput::privilegeSlugs($payload['privilegeSlugs']),
            AccountRequestInput::accessControl($this->request->getAttribute(AccessControl::class)),
        ));

        return new JsonResponse(['accountUuid' => $accountId->toString()], 201);
    }
}
