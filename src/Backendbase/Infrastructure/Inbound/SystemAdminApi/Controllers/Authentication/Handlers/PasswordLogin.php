<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Inbound\SystemAdminApi\Controllers\Authentication\Handlers;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Infrastructure\Adapters\Http\Actions\Action;
use Backendbase\Infrastructure\Adapters\Http\Identity\IdentityHttpDispatch;
use Backendbase\Infrastructure\Adapters\Http\Identity\IdentityHttpInput;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

final class PasswordLogin extends Action
{
    public function __construct(LoggerInterface $logger, private IdentityHttpInput $input, private IdentityHttpDispatch $dispatch)
    {
        parent::__construct($logger);
    }

    protected function action(): ResponseInterface
    {
        $request = $this->request;
        $realm   = $request->getAttribute('expectedRealm');
        $action  = $request->getAttribute('identityAction');
        if ($realm !== Realm::ADMIN || $action !== 'login') {
            throw new RuntimeException('A trusted authentication route policy is required.');
        }

        $identifier = IdentityId::create();
        $input      = $this->input;
        $command    = $input->command($identifier->toString(), $action, $realm, $request);
        $dispatch   = $this->dispatch;

        return $dispatch->execute($command, 'session');
    }
}
