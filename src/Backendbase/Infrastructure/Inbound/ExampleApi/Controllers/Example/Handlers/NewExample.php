<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Inbound\ExampleApi\Controllers\Example\Handlers;

use Backendbase\Domain\ExampleCatalog\Contracts\Command\AddEntry;
use Backendbase\Infrastructure\Adapters\Http\Actions\Action;
use Backendbase\Infrastructure\Adapters\Http\Input\RequestObjectInput;
use Backendbase\Infrastructure\Inbound\ExampleApi\Controllers\Example\ExampleRequestBody;
use Backendbase\Infrastructure\Inbound\ExampleApi\Controllers\Example\ExampleRequestInput;
use Backendbase\Shared\CQRS\CommandBus;
use Laminas\Diactoros\Response\EmptyResponse;
use Override;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Uid\Ulid;

use function strtolower;

class NewExample extends Action
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
        $type         = (string) $this->request->getAttribute('type-slug');
        $group        = ExampleRequestInput::group($this->request->getAttribute('example-group'));
        $payload      = ExampleRequestBody::read(
            $this->request,
            ['typeTargetId', 'lookupKey', 'lookupValue', 'details', 'isActive'],
        );
        $typeTargetId = RequestObjectInput::positiveIntegerOrNull($payload['typeTargetId'] ?? null, 'typeTargetId');
        $entryId      = Uuid::uuid7()->toString();
        $lookupKey    = ExampleRequestInput::optionalString(
            $payload['lookupKey'] ?? null,
            'lookupKey',
            strtolower(Ulid::generate()),
        );
        $lookupValue  = ExampleRequestInput::requiredString($payload['lookupValue'] ?? null, 'lookupValue');
        $details      = ExampleRequestInput::optionalObject($payload['details'] ?? null, 'details');
        $isActive     = ExampleRequestInput::optionalBoolean($payload['isActive'] ?? null, 'isActive', true);

        $command = new AddEntry(
            $entryId,
            ExampleRequestInput::type($type),
            $typeTargetId,
            $group,
            $isActive,
            $lookupKey,
            $lookupValue,
            $details,
        );

        $this->commandBus->handle($command);

        return new EmptyResponse(204, ['Backendbase-Insert-Id' => $entryId]);
    }
}
