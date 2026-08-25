<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Example\Handlers;

use Backendbase\Domain\ExampleBoundedContext\Contracts\Query\GetExamplesByGroup;
use Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Example\ExampleRequestInput;
use Backendbase\Shared\CQRS\QueryBus;
use Backendbase\Shared\Http\Actions\Action;
use Backendbase\Shared\Primitives\Pagination;
use Backendbase\Shared\Services\Translator;
use Backendbase\Shared\Settings;
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
        private readonly Settings $settings,
        protected LoggerInterface $logger,
        protected Translator|null $translator,
    ) {
        parent::__construct($logger, $translator);
    }

    #[Override]
    protected function action(): Response
    {
        $cdnBaseUrl   = $this->settings->get('cdnBaseUrl');
        $type         = ExampleRequestInput::type($this->request->getAttribute('typeSlug'));
        $group        = (string) $this->request->getAttribute('exampleGroup');
        $params       = PayloadSanitizer::sanitize($this->request->getQueryParams());
        $typeTargetId = ExampleRequestInput::optionalTypeTargetId($params['typeTargetId'] ?? null);
        $pageSize     = ExampleRequestInput::positiveInteger($params['pageSize'] ?? 1000, 'pageSize');
        $page         = ExampleRequestInput::positiveInteger($params['page'] ?? 1, 'page');

        $pagination = new Pagination($pageSize, $page);

        $pageResult = $this->queryBus->handle(new GetExamplesByGroup($type, $typeTargetId, $group, $pagination));
        $data       = [];
        foreach ($pageResult->items() as $example) {
            $details = [];

            foreach ($example->details() as $key => $value) {
                $details[$key] = $value;
                if (! str_contains((string) $key, 'image') && ! str_contains((string) $key, 'Image')) {
                    continue;
                }

                $details[$key . 'Url'] = $cdnBaseUrl . $value;
            }

            $data[] = [
                'uuid' => $example->uuid(),
                'type' => $type->value,
                'typeTargetId' => $typeTargetId,
                'exampleGroup' => $group,
                'lookupKey' => $example->lookupKey(),
                'lookupValue' => $example->lookupValue(),
                'isActive' => $example->isActive(),
                'details' => $details,
                'createdAt' => $example->createdAt()->format(DATE_ATOM),
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
