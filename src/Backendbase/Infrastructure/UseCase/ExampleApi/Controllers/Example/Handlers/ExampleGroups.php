<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Example\Handlers;

use Backendbase\Domain\ExampleBoundedContext\Contracts\Query\GetExampleGroupsByType;
use Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Example\ExampleRequestInput;
use Backendbase\Shared\CQRS\QueryBus;
use Backendbase\Shared\Http\Actions\Action;
use Backendbase\Shared\Primitives\Pagination;
use Backendbase\Utility\Arrays\PayloadSanitizer;
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
        $type         = ExampleRequestInput::type($this->request->getAttribute('type-slug'));
        $params       = PayloadSanitizer::sanitize($this->request->getQueryParams());
        $typeTargetId = ExampleRequestInput::optionalTypeTargetId($params['typeTargetId'] ?? null);
        $pageSize     = ExampleRequestInput::positiveInteger($params['pageSize'] ?? 1000, 'pageSize');
        $page         = ExampleRequestInput::positiveInteger($params['page'] ?? 1, 'page');

        $pagination = new Pagination($pageSize, $page);
        $result     = $this->queryBus->handle(new GetExampleGroupsByType($type, $typeTargetId, $pagination));

        return new JsonResponse([
            'pageSize' => $pageSize,
            'page' => $page,
            'total' => $result->total(),
            'exampleGroups' => $result->items(),
        ], 200);
    }
}
