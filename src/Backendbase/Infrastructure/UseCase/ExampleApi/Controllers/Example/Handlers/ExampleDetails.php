<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Example\Handlers;

use Backendbase\Domain\ExampleBoundedContext\Contracts\Query\GetExampleByCriteria;
use Backendbase\Domain\ExampleBoundedContext\Contracts\ReadModel\ExampleDetails as ExampleDetailsReadModel;
use Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Example\ExampleRequestInput;
use Backendbase\Shared\CQRS\QueryBus;
use Backendbase\Shared\Exception\ResourceNotFound;
use Backendbase\Shared\Http\Actions\Action;
use Backendbase\Shared\Services\Translator;
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
        protected Translator|null $translator,
    ) {
        parent::__construct($logger, $translator);
    }

    #[Override]
    protected function action(): Response
    {
        $type         = ExampleRequestInput::type($this->request->getAttribute('typeSlug'));
        $group        = (string) $this->request->getAttribute('exampleGroup');
        $exampleKey   = (string) $this->request->getAttribute('exampleKey');
        $params       = PayloadSanitizer::sanitize($this->request->getQueryParams());
        $typeTargetId = ExampleRequestInput::optionalTypeTargetId($params['typeTargetId'] ?? null);

        $example = $this->queryBus->handle(new GetExampleByCriteria($type, $typeTargetId, $group, $exampleKey));
        if (! $example instanceof ExampleDetailsReadModel) {
            throw ResourceNotFound::create('The example was not found.');
        }

        $details = $example->details();
        $data    = [
            'uuid' => $example->uuid(),
            'type' => $example->type()->value,
            //   'typeTargetId' => $example->typeTargetId(),
            'exampleGroup' => $example->group(),
            'lookupKey' => $example->lookupKey(),
            'lookupValue' => $example->lookupValue(),
            'details' => $details,
            'isActive' => $example->isActive(),
            'updatedAt' => $example->updatedAt()->format(DATE_ATOM),
            'createdAt' => $example->createdAt()->format(DATE_ATOM),
        ];

        return new JsonResponse(['example' => $data], 200);
    }
}
