<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Inbound\ExampleApi\Controllers\Example\Handlers;

use Backendbase\Domain\ExampleCatalog\Contracts\Query\GetEntryGroupsByType;
use Backendbase\Infrastructure\Adapters\Http\Actions\Action;
use Backendbase\Infrastructure\Inbound\ExampleApi\Controllers\Example\ExampleRequestInput;
use Backendbase\Shared\CQRS\QueryBus;
use Backendbase\Shared\Primitives\Pagination;
use Laminas\Diactoros\Response\JsonResponse;
use Override;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

class ExampleGroups extends Action
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
        $type            = ExampleRequestInput::type($this->request->getAttribute('type-slug'));
        $queryParameters = $this->request->getQueryParams();
        $typeTargetId    = ExampleRequestInput::optionalTypeTargetId($queryParameters['typeTargetId'] ?? null);
        $pageSize        = ExampleRequestInput::positiveInteger($queryParameters['pageSize'] ?? 1000, 'pageSize');
        $page            = ExampleRequestInput::positiveInteger($queryParameters['page'] ?? 1, 'page');

        $pagination = new Pagination($pageSize, $page);
        $result     = $this->queryBus->handle(new GetEntryGroupsByType($type, $typeTargetId, $pagination));

        return new JsonResponse([
            'pageSize' => $pageSize,
            'page' => $page,
            'total' => $result->total(),
            'exampleGroups' => $result->items(),
        ], 200);
    }
}
