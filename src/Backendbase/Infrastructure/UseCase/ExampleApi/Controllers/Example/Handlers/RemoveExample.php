<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Example\Handlers;

use Backendbase\Domain\ExampleBoundedContext\Contracts\Command\RemoveExample as RemoveExampleCommand;
use Backendbase\Domain\ExampleBoundedContext\Contracts\Query\GetExampleIdByCriteria;
use Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Example\ExampleRequestInput;
use Backendbase\Shared\CQRS\CommandBus;
use Backendbase\Shared\CQRS\QueryBus;
use Backendbase\Shared\Exception\ResourceNotFound;
use Backendbase\Shared\Http\Actions\Action;
use Backendbase\Shared\Services\Translator;
use Backendbase\Utility\Arrays\PayloadSanitizer;
use Laminas\Diactoros\Response\EmptyResponse;
use Override;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

class RemoveExample extends Action
{
    public function __construct(
        private readonly QueryBus $queryBus,
        private readonly CommandBus $commandBus,
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

        $exampleId = $this->queryBus->handle(new GetExampleIdByCriteria($type, $typeTargetId, $group, $exampleKey));
        if ($exampleId === null) {
            throw ResourceNotFound::create('The example was not found.');
        }

        $this->commandBus->handle(new RemoveExampleCommand($exampleId));

        return new EmptyResponse(204);
    }
}
