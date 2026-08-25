<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Example\Handlers;

use Backendbase\Domain\ExampleBoundedContext\Contracts\Command\AddNewExample;
use Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Example\ExampleRequestInput;
use Backendbase\Shared\CQRS\CommandBus;
use Backendbase\Shared\Http\Actions\Action;
use Backendbase\Shared\Services\Translator;
use Backendbase\Utility\Arrays\PayloadSanitizer;
use Laminas\Diactoros\Response\EmptyResponse;
use Override;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Uid\Ulid;

use function htmlspecialchars_decode;
use function strtolower;

class NewExample extends Action
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
        $type         = (string) $this->request->getAttribute('typeSlug');
        $group        = (string) $this->request->getAttribute('exampleGroup');
        $payload      = PayloadSanitizer::sanitize($this->request->getParsedBody());
        $typeTargetId = ExampleRequestInput::optionalTypeTargetId($payload['typeTargetId'] ?? null);
        $exampleId    = Uuid::uuid7()->toString();

        $command = new AddNewExample(
            $exampleId,
            ExampleRequestInput::type($type),
            $typeTargetId,
            $group,
            (bool) ($payload['isActive'] ?? true),
            $payload['lookupKey'] ?? strtolower(Ulid::generate()),
            htmlspecialchars_decode((string) $payload['lookupValue']),
            $payload['details'] ?? [],
        );

        $this->commandBus->handle($command);

        return new EmptyResponse(204, ['Backendbase-Insert-Id' => $exampleId]);
    }
}
