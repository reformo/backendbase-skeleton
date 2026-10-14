<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Inbound\IdentityApi\Controllers;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Infrastructure\Adapters\Http\Actions\Action;
use Backendbase\Infrastructure\Inbound\IdentityApi\Http\AdministrationHttpInput;
use Backendbase\Infrastructure\Inbound\IdentityApi\Http\IdentityHttpDispatch;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

use function is_string;

final class Administration extends Action
{
    public function __construct(LoggerInterface $logger, private AdministrationHttpInput $input, private IdentityHttpDispatch $dispatch)
    {
        parent::__construct($logger);
    }

    protected function action(): ResponseInterface
    {
        $request = $this->request;
        $action  = $request->getAttribute('identityAction');
        if (! is_string($action)) {
            throw new RuntimeException('A trusted administration route policy is required.');
        }

        $identifier = IdentityId::create();
        $input      = $this->input;
        $command    = $input->command($identifier->toString(), $action, $request);
        $dispatch   = $this->dispatch;

        return $dispatch->execute($command, self::responseKey($action));
    }

    private static function responseKey(string $action): string
    {
        return match ($action) {
            'invite-admin' => 'invitation',
            'approve-admin' => 'admin',
            'change-actor' => 'actor',
            default => throw new RuntimeException('The administration response key is not configured.'),
        };
    }
}
