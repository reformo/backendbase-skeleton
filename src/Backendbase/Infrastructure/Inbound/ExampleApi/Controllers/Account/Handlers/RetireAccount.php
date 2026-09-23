<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Inbound\ExampleApi\Controllers\Account\Handlers;

use Backendbase\Domain\IdentityAndAccess\Contracts\Command\RetireAccount as RetireAccountCommand;
use Backendbase\Infrastructure\Adapters\Http\Actions\Action;
use Backendbase\Infrastructure\Inbound\ExampleApi\Controllers\Account\AccountRequestInput;
use Backendbase\Shared\Authorization\AccessControl;
use Backendbase\Shared\CQRS\CommandBus;
use Laminas\Diactoros\Response\EmptyResponse;
use Override;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

final class RetireAccount extends Action
{
    public function __construct(private readonly CommandBus $commandBus, LoggerInterface $logger)
    {
        parent::__construct($logger);
    }

    #[Override]
    protected function action(): Response
    {
        $this->commandBus->handle(new RetireAccountCommand(
            AccountRequestInput::accountId($this->request->getAttribute('account-uuid')),
            AccountRequestInput::accessControl($this->request->getAttribute(AccessControl::class)),
        ));

        return new EmptyResponse(204);
    }
}
