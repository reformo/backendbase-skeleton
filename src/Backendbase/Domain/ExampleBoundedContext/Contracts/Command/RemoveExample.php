<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleBoundedContext\Contracts\Command;

use Backendbase\Domain\ExampleBoundedContext\Application\CommandHandlers\RemoveExampleHandler;
use Backendbase\Domain\ExampleBoundedContext\Domain\ExampleIdentity;
use Backendbase\Shared\Authorization\AccessControl;
use Backendbase\Shared\CQRS\Attributes\CQRSHandler;
use Backendbase\Shared\CQRS\Command;
use Override;

#[CQRSHandler(RemoveExampleHandler::class)]
readonly class RemoveExample implements Command
{
    public function __construct(
        private ExampleIdentity $identity,
        private AccessControl $accessControl,
    ) {
    }

    public function identity(): ExampleIdentity
    {
        return $this->identity;
    }

    public function accessControl(): AccessControl
    {
        return $this->accessControl;
    }

    #[Override]
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['identity' => $this->identity->toArray()];
    }
}
