<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Greeting\Handlers;

use Backendbase\Domain\ExampleBoundedContext\Contracts\Command\QueueGreeting;
use Backendbase\Domain\IdentityAndAccess\Exception\AuthorizationExpired;
use Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Greeting\GreetingRequestInput;
use Backendbase\Shared\Authorization\AccessControl;
use Backendbase\Shared\CQRS\CommandBus;
use Backendbase\Shared\Http\Actions\Action;
use Laminas\Diactoros\Response\EmptyResponse;
use Override;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

final class QueueGreetingRequest extends Action
{
    public function __construct(private readonly CommandBus $commandBus, LoggerInterface $logger)
    {
        parent::__construct($logger);
    }

    #[Override]
    protected function action(): Response
    {
        $fullName      = GreetingRequestInput::fullName($this->request->getParsedBody());
        $accessControl = $this->request->getAttribute(AccessControl::class);
        if (! $accessControl instanceof AccessControl) {
            throw AuthorizationExpired::create('The authorization context is missing.');
        }

        $this->commandBus->handle(new QueueGreeting($fullName, $accessControl));

        return new EmptyResponse(202);
    }
}
