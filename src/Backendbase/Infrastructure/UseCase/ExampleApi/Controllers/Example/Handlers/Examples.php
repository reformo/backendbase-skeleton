<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Example\Handlers;

use Backendbase\Domain\ExampleCatalog\Contracts\Query\GetEntriesByGroup;
use Backendbase\Infrastructure\Adapters\Http\Actions\Action;
use Backendbase\Infrastructure\Configuration\ApplicationRuntimeSettings;
use Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Example\ExampleRequestInput;
use Backendbase\Shared\CQRS\QueryBus;
use Backendbase\Shared\Primitives\Pagination;
use Backendbase\Utility\Arrays\PayloadSanitizer;
use Laminas\Diactoros\Response\JsonResponse;
use Override;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

use function str_contains;

use const DATE_ATOM;

class Examples extends Action
{
    public function __construct(
        private readonly QueryBus $queryBus,
        private readonly ApplicationRuntimeSettings $settings,
        protected LoggerInterface $logger,
    ) {
        parent::__construct($logger);
    }

    #[Override]
    protected function action(): Response
    {
        $cdnBaseUrl   = $this->settings->cdnBaseUrl();
        $type         = ExampleRequestInput::type($this->request->getAttribute('type-slug'));
        $group        = (string) $this->request->getAttribute('example-group');
        $params       = PayloadSanitizer::sanitize($this->request->getQueryParams());
        $typeTargetId = ExampleRequestInput::optionalTypeTargetId($params['typeTargetId'] ?? null);
        $pageSize     = ExampleRequestInput::positiveInteger($params['pageSize'] ?? 1000, 'pageSize');
        $page         = ExampleRequestInput::positiveInteger($params['page'] ?? 1, 'page');

        $pagination = new Pagination($pageSize, $page);

        $pageResult = $this->queryBus->handle(new GetEntriesByGroup($type, $typeTargetId, $group, $pagination));
        $data       = [];
        foreach ($pageResult->items() as $entry) {
            $details = [];

            foreach ($entry->details() as $key => $value) {
                $details[$key] = $value;
                if (! str_contains((string) $key, 'image') && ! str_contains((string) $key, 'Image')) {
                    continue;
                }

                $details[$key . 'Url'] = $cdnBaseUrl . $value;
            }

            $data[] = [
                'uuid' => $entry->uuid(),
                'type' => $type->value,
                'typeTargetId' => $typeTargetId,
                'exampleGroup' => $group,
                'lookupKey' => $entry->lookupKey(),
                'lookupValue' => $entry->lookupValue(),
                'isActive' => $entry->isActive(),
                'details' => $details,
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
