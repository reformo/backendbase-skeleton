<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Example\Handlers;

use Backendbase\Domain\ExampleBoundedContext\Contracts\Command\ChangeExample;
use Backendbase\Domain\ExampleBoundedContext\Domain\ExampleIdentity;
use Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Example\ExampleRequestInput;
use Backendbase\Shared\CQRS\CommandBus;
use Backendbase\Shared\Http\Actions\Action;
use Backendbase\Shared\Services\Translator;
use Backendbase\Utility\Arrays\PayloadSanitizer;
use Laminas\Diactoros\Response\EmptyResponse;
use Override;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

class ChangeExampleDetails extends Action
{
    public function __construct(
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
        $payload      = PayloadSanitizer::sanitize($this->request->getParsedBody());
        $typeTargetId = ExampleRequestInput::optionalTypeTargetId($payload['typeTargetId'] ?? null);

        $payload['lookupValue'] ??= null;
        $payload['details']     ??= null;
        $payload['isActive']    ??= null;

        $identity = new ExampleIdentity($type, $typeTargetId, $group, $exampleKey);
        $command  = new ChangeExample($identity)
            ->setDetails($payload['details'] ?? null)
            ->setValue($payload['lookupValue'] ?? null)
            ->setIsActive($payload['isActive'] ?? null);
        $this->commandBus->handle($command);

        return new EmptyResponse(204);
    }
}
