<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Inbound\ExampleApi\Controllers\Root;

use Backendbase\Domain\IdentityAndAccess\Application\AuthenticateAccount;
use Backendbase\Infrastructure\Adapters\Http\Actions\Action;
use Laminas\Diactoros\Response\JsonResponse;
use Override;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use SensitiveParameterValue;

class Authenticate extends Action
{
    public function __construct(
        private readonly AuthenticateAccount $authenticateAccount,
        LoggerInterface $logger,
    ) {
        parent::__construct($logger);
    }

    #[Override]
    protected function action(): Response
    {
        $payload     = AuthenticationRequestInput::payload($this->request->getParsedBody());
        $email       = AuthenticationRequestInput::email($payload['email'] ?? null);
        $password    = AuthenticationRequestInput::password($payload['password'] ?? null);
        $accessToken = $this->authenticateAccount->authenticate($email, new SensitiveParameterValue($password));

        return new JsonResponse(['accessToken' => $accessToken], 201);
    }
}
