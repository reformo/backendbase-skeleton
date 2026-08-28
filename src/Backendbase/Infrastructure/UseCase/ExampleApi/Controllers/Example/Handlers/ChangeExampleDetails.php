<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Example\Handlers;

use Backendbase\Domain\ExampleBoundedContext\Contracts\Command\ChangeExample;
use Backendbase\Domain\ExampleBoundedContext\Domain\ExampleIdentity;
use Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Example\ExampleRequestInput;
use Backendbase\Shared\Authorization\AccessControl;
use Backendbase\Shared\CQRS\CommandBus;
use Backendbase\Shared\Http\Actions\Action;
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
    ) {
        parent::__construct($logger);
    }

    #[Override]
    protected function action(): Response
    {
        $type         = ExampleRequestInput::type($this->request->getAttribute('typeSlug'));
        $group        = (string) $this->request->getAttribute('exampleGroup');
        $exampleKey   = (string) $this->request->getAttribute('exampleKey');
        $payload      = PayloadSanitizer::sanitize($this->request->getParsedBody());
        $typeTargetId = ExampleRequestInput::optionalTypeTargetId($payload['typeTargetId'] ?? null);
        $lookupValue  = ExampleRequestInput::optionalStringOrNull($payload['lookupValue'] ?? null, 'lookupValue');
        $details      = ExampleRequestInput::optionalObjectOrNull($payload['details'] ?? null, 'details');
        $isActive     = ExampleRequestInput::optionalBooleanOrNull($payload['isActive'] ?? null, 'isActive');

        $identity      = new ExampleIdentity($type, $typeTargetId, $group, $exampleKey);
        $accessControl = ExampleRequestInput::accessControl($this->request->getAttribute(AccessControl::class));
        $command       = new ChangeExample($identity, $accessControl)
            ->setDetails($details)
            ->setValue($lookupValue)
            ->setIsActive($isActive);
        $this->commandBus->handle($command);

        return new EmptyResponse(204);
    }
}
