<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Account\Handlers;

use Backendbase\Domain\IdentityAndAccess\Contracts\Query\ListAccounts;
use Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Account\AccountRequestInput;
use Backendbase\Shared\Authorization\AccessControl;
use Backendbase\Shared\CQRS\QueryBus;
use Backendbase\Shared\Http\Actions\Action;
use Laminas\Diactoros\Response\JsonResponse;
use Override;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

use const DATE_ATOM;

final class Accounts extends Action
{
    public function __construct(private readonly QueryBus $queryBus, LoggerInterface $logger)
    {
        parent::__construct($logger);
    }

    #[Override]
    protected function action(): Response
    {
        $accounts = $this->queryBus->handle(new ListAccounts(
            AccountRequestInput::accessControl($this->request->getAttribute(AccessControl::class)),
        ));
        $data     = [];
        foreach ($accounts as $account) {
            $data[] = [
                'uuid' => $account->uuid(),
                'email' => $account->email(),
                'privilegeSlugs' => $account->privilegeSlugs(),
                'createdAt' => $account->createdAt()->format(DATE_ATOM),
            ];
        }

        return new JsonResponse(['accounts' => $data]);
    }
}
