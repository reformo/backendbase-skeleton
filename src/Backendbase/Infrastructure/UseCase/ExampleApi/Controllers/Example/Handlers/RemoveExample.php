<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Example\Handlers;

use Backendbase\Domain\ExampleCatalog\Contracts\Command\RemoveEntry as RemoveEntryCommand;
use Backendbase\Domain\ExampleCatalog\Domain\EntryIdentity;
use Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Example\ExampleRequestInput;
use Backendbase\Shared\Authorization\AccessControl;
use Backendbase\Shared\CQRS\CommandBus;
use Backendbase\Shared\Http\Actions\Action;
use Backendbase\Utility\Arrays\PayloadSanitizer;
use Laminas\Diactoros\Response\EmptyResponse;
use Override;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

class RemoveExample extends Action
{
    public function __construct(
        private readonly CommandBus $commandBus,
        protected LoggerInterface $logger,
    ) {
        parent::__construct($logger);
    }

    #[Override]
    protected function action(): Response
    {
        $type         = ExampleRequestInput::type($this->request->getAttribute('type-slug'));
        $group        = (string) $this->request->getAttribute('example-group');
        $entryKey     = (string) $this->request->getAttribute('example-key');
        $params       = PayloadSanitizer::sanitize($this->request->getQueryParams());
        $typeTargetId = ExampleRequestInput::optionalTypeTargetId($params['typeTargetId'] ?? null);

        $identity      = new EntryIdentity($type, $typeTargetId, $group, $entryKey);
        $accessControl = ExampleRequestInput::accessControl($this->request->getAttribute(AccessControl::class));
        $this->commandBus->handle(new RemoveEntryCommand($identity, $accessControl));

        return new EmptyResponse(204);
    }
}
