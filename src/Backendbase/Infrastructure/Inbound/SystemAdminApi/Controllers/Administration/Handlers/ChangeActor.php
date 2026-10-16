<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Inbound\SystemAdminApi\Controllers\Administration\Handlers;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Infrastructure\Adapters\Http\Actions\Action;
use Backendbase\Infrastructure\Adapters\Http\Identity\IdentityHttpDispatch;
use Backendbase\Infrastructure\Inbound\SystemAdminApi\Controllers\Administration\AdministrationHttpInput;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

final class ChangeActor extends Action
{
    public function __construct(LoggerInterface $logger, private AdministrationHttpInput $input, private IdentityHttpDispatch $dispatch)
    {
        parent::__construct($logger);
    }

    protected function action(): ResponseInterface
    {
        $request = $this->request;
        $action  = $request->getAttribute('identityAction');
        if ($action !== 'change-actor') {
            throw new RuntimeException('A trusted administration route policy is required.');
        }

        $identifier = IdentityId::create();
        $input      = $this->input;
        $command    = $input->command($identifier->toString(), $action, $request);
        $dispatch   = $this->dispatch;

        return $dispatch->execute($command, 'actor');
    }
}
