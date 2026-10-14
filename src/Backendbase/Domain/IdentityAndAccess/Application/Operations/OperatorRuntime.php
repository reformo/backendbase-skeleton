<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\Operations;

use Backendbase\Domain\IdentityAndAccess\Contracts\AuthenticationSecrets;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\OperatorInput;
use Backendbase\Shared\Time\Clock;

final readonly class OperatorRuntime
{
    public function __construct(private AuthenticationSecrets $secrets, private Clock $clock)
    {
    }

    public function execute(OperatorWorkflow $workflow, OperatorInput $input, bool $apply): string
    {
        $clock = $this->clock;

        return $workflow->run($input, $this->secrets, $clock->now(), $apply);
    }
}
