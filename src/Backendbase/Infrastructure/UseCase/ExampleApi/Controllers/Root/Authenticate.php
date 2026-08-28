<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Root;

use Backendbase\Domain\IdentityAndAccess\Contracts\TokenIssuer;
use Backendbase\Shared\Http\Actions\Action;
use Backendbase\Shared\Services\Translator;
use Laminas\Diactoros\Response\JsonResponse;
use Override;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

class Authenticate extends Action
{
    public function __construct(
        private readonly TokenIssuer $tokenIssuer,
        LoggerInterface $logger,
        Translator|null $translator = null,
    ) {
        parent::__construct($logger, $translator);
    }

    #[Override]
    protected function action(): Response
    {
        $payload = AuthenticationRequestInput::payload($this->request->getParsedBody());
        $email   = AuthenticationRequestInput::email($payload['email'] ?? null);
        AuthenticationRequestInput::password($payload['password'] ?? null);

        $userId      = '019ee8a6-903a-75cf-b9a4-e19d6d0db533';
        $userData    = [
            'id' => 1,
            'uuid' => $userId,
            'email' => $email,
            'firstName' => 'Jane',
            'familyName' => 'Doe',
            'privileges' => ['example.add', 'example.change', 'example.remove'],
        ];
        $accessToken = $this->tokenIssuer->issueNewToken('userId', $userId, $userData);

        return new JsonResponse(['accessToken' => $accessToken], 201);
    }
}
