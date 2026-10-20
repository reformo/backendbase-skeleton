<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\Operations;

use Backendbase\Domain\IdentityAndAccess\Contracts\Input\OperatorInput;
use Backendbase\Shared\Time\Clock;

final readonly class OperatorRuntime
{
    public function __construct(private OperatorWorkflow $workflow, private Clock $clock)
    {
    }

    public function execute(OperatorInput $input, bool $apply): string
    {
        $clock    = $this->clock;
        $workflow = $this->workflow;

        return $workflow->run($input, $clock->now(), $apply);
    }
}
