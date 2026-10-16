<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Inbound\ExampleApi\Controllers\Example\Handlers;

use Backendbase\Domain\ExampleCatalog\Contracts\Command\ChangeEntry;
use Backendbase\Domain\ExampleCatalog\Domain\EntryIdentity;
use Backendbase\Infrastructure\Adapters\Http\Actions\Action;
use Backendbase\Infrastructure\Adapters\Http\Input\RequestObjectInput;
use Backendbase\Infrastructure\Inbound\ExampleApi\Controllers\Example\ExampleRequestBody;
use Backendbase\Infrastructure\Inbound\ExampleApi\Controllers\Example\ExampleRequestInput;
use Backendbase\Shared\CQRS\CommandBus;
use Laminas\Diactoros\Response\EmptyResponse;
use Override;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

class ChangeExampleDetails extends Action
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
        $group        = ExampleRequestInput::group($this->request->getAttribute('example-group'));
        $entryKey     = ExampleRequestInput::key($this->request->getAttribute('example-key'));
        $payload      = ExampleRequestBody::read(
            $this->request,
            ['typeTargetId', 'lookupValue', 'details', 'isActive'],
        );
        $typeTargetId = RequestObjectInput::positiveIntegerOrNull($payload['typeTargetId'] ?? null, 'typeTargetId');
        $lookupValue  = ExampleRequestInput::optionalStringOrNull($payload['lookupValue'] ?? null, 'lookupValue');
        $details      = ExampleRequestInput::optionalObjectOrNull($payload['details'] ?? null, 'details');
        $isActive     = ExampleRequestInput::optionalBooleanOrNull($payload['isActive'] ?? null, 'isActive');

        $identity = new EntryIdentity($type, $typeTargetId, $group, $entryKey);
        $command  = new ChangeEntry($identity)
            ->setDetails($details)
            ->setValue($lookupValue)
            ->setIsActive($isActive);
        $this->commandBus->handle($command);

        return new EmptyResponse(204);
    }
}
