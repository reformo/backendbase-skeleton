<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Inbound\ExampleApi\Controllers\Example\Handlers;

use Backendbase\Domain\ExampleCatalog\Contracts\Query\GetEntryByCriteria;
use Backendbase\Domain\ExampleCatalog\Contracts\ReadModel\EntryDetails as EntryDetailsReadModel;
use Backendbase\Infrastructure\Adapters\Http\Actions\Action;
use Backendbase\Infrastructure\Inbound\ExampleApi\Controllers\Example\ExampleRequestInput;
use Backendbase\Shared\CQRS\QueryBus;
use Backendbase\Shared\Exception\ResourceNotFound;
use Backendbase\Utility\Arrays\PayloadSanitizer;
use Laminas\Diactoros\Response\JsonResponse;
use Override;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

use const DATE_ATOM;

class ExampleDetails extends Action
{
    public function __construct(
        private readonly QueryBus $queryBus,
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

        $entry = $this->queryBus->handle(new GetEntryByCriteria($type, $typeTargetId, $group, $entryKey));
        if (! $entry instanceof EntryDetailsReadModel) {
            throw ResourceNotFound::create('The example was not found.');
        }

        $details = $entry->details();
        $data    = [
            'uuid' => $entry->uuid(),
            'type' => $entry->type()->value,
            'typeTargetId' => $entry->typeTargetId(),
            'exampleGroup' => $entry->group(),
            'lookupKey' => $entry->lookupKey(),
            'lookupValue' => $entry->lookupValue(),
            'details' => $details,
            'isActive' => $entry->isActive(),
            'updatedAt' => $entry->updatedAt()->format(DATE_ATOM),
            'createdAt' => $entry->createdAt()->format(DATE_ATOM),
        ];

        return new JsonResponse(['example' => $data], 200);
    }
}
