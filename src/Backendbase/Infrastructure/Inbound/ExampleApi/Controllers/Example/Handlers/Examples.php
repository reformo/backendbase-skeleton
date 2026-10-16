<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Inbound\ExampleApi\Controllers\Example\Handlers;

use Backendbase\Domain\ExampleCatalog\Contracts\Query\GetEntriesByGroup;
use Backendbase\Infrastructure\Adapters\Http\Actions\Action;
use Backendbase\Infrastructure\Inbound\ExampleApi\Controllers\Example\ExampleRequestInput;
use Backendbase\Shared\CQRS\QueryBus;
use Backendbase\Shared\Primitives\Pagination;
use Laminas\Diactoros\Response\JsonResponse;
use Override;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

use const DATE_ATOM;

class Examples extends Action
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
        $group        = ExampleRequestInput::group($this->request->getAttribute('example-group'));
        $params       = $this->request->getQueryParams();
        $typeTargetId = ExampleRequestInput::optionalTypeTargetId($params['typeTargetId'] ?? null);
        $pageSize     = ExampleRequestInput::positiveInteger($params['pageSize'] ?? 1000, 'pageSize');
        $page         = ExampleRequestInput::positiveInteger($params['page'] ?? 1, 'page');

        $pagination = new Pagination($pageSize, $page);

        $pageResult = $this->queryBus->handle(new GetEntriesByGroup($type, $typeTargetId, $group, $pagination));
        $data       = [];
        foreach ($pageResult->items() as $entry) {
            $details = $entry->details();

            $data[] = [
                'uuid' => $entry->uuid(),
                'type' => $type->value,
                'typeTargetId' => $typeTargetId,
                'exampleGroup' => $group,
                'lookupKey' => $entry->lookupKey(),
                'lookupValue' => $entry->lookupValue(),
                'isActive' => $entry->isActive(),
                'details' => (object) $details,
                'createdAt' => $entry->createdAt()->format(DATE_ATOM),
            ];
        }

        return new JsonResponse([
            'pageSize' => $pageSize,
            'page' => $page,
            'total' => $pageResult->total(),
            'examples' => $data,
        ], 200);
    }
}
