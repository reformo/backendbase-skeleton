<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Account\Handlers;

use Backendbase\Domain\IdentityAndAccess\Contracts\Command\ReviseAccount as ReviseAccountCommand;
use Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Account\AccountRequestInput;
use Backendbase\Shared\Authorization\AccessControl;
use Backendbase\Shared\CQRS\CommandBus;
use Backendbase\Shared\Http\Actions\Action;
use Laminas\Diactoros\Response\EmptyResponse;
use Override;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

final class ReviseAccount extends Action
{
    public function __construct(private readonly CommandBus $commandBus, LoggerInterface $logger)
    {
        parent::__construct($logger);
    }

    #[Override]
    protected function action(): Response
    {
        $payload = AccountRequestInput::revisionPayload($this->request->getParsedBody());
        $this->commandBus->handle(new ReviseAccountCommand(
            AccountRequestInput::accountId($this->request->getAttribute('account-uuid')),
            AccountRequestInput::optionalEmail($payload),
            AccountRequestInput::optionalPasswordHash($payload),
            AccountRequestInput::optionalPrivilegeSlugs($payload),
            AccountRequestInput::accessControl($this->request->getAttribute(AccessControl::class)),
        ));

        return new EmptyResponse(204);
    }
}
