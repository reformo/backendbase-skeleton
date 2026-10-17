<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Inbound\ExampleApi\Controllers\Authentication\Handlers;

use Backendbase\Domain\IdentityAndAccess\Contracts\Command\Contacts\VerifyContact;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Infrastructure\Adapters\Http\Actions\Action;
use Backendbase\Infrastructure\Adapters\Http\Identity\ContactHttpInput;
use Backendbase\Infrastructure\Adapters\Http\Identity\IdentityHttpDispatch;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

final class RequestContactVerification extends Action
{
    public function __construct(LoggerInterface $logger, private ContactHttpInput $input, private IdentityHttpDispatch $dispatch)
    {
        parent::__construct($logger);
    }

    protected function action(): ResponseInterface
    {
        $input    = $this->input;
        $attempt  = $input->read($this->request, $this->args);
        $id       = IdentityId::create();
        $command  = new VerifyContact($id->toString(), $attempt);
        $dispatch = $this->dispatch;

        return $dispatch->execute($command, 'contactInfo');
    }
}
