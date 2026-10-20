<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Inbound\IdentityApi\Sessions;

use Backendbase\Domain\IdentityAndAccess\Application\IdentityOperation;
use Backendbase\Domain\IdentityAndAccess\Application\SessionIssuance;
use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityCommand;
use Backendbase\Domain\IdentityAndAccess\Contracts\ReadModel\IdentityOperationResult;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use Backendbase\Infrastructure\Adapters\Http\Identity\IdentityHttpInput;
use Backendbase\Infrastructure\Adapters\Http\Identity\Sessions\RefreshAuthentication;
use Backendbase\Infrastructure\Adapters\Http\Identity\Sessions\SessionHttpDispatch;
use Backendbase\Infrastructure\Adapters\Http\Identity\Sessions\SessionResponseGuard;
use Backendbase\Shared\CQRS\CommandBus;
use Backendbase\Shared\CQRS\QueryBus;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

final class SessionBoundaryTest extends TestCase
{
    public function testExpiredBrowserCookieRequiresANewLogin(): void
    {
        [, $api]  = SessionHttpScenario::login();
        $response = $api->request('POST', '/auth/refresh', ['refreshTokenTransport' => 'cookie'], headers: ['Origin' => 'https://app.example.com']);
        self::assertSame(401, $response->getStatusCode());
    }

    public function testRenewalRequiresJsonContentType(): void
    {
        [, $api, $session] = SessionHttpScenario::login();
        $response          = $api->request('POST', '/auth/refresh', ['refreshToken' => $session['refreshToken']], headers: ['Content-Type' => 'text/plain']);
        self::assertSame(400, $response->getStatusCode());
    }

    public function testRenewalMiddlewareRequiresATrustedRealm(): void
    {
        $fixture    = IdentityFixture::create();
        $container  = $fixture->container();
        $middleware = $container->get(RefreshAuthentication::class);
        $handler    = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');
        $this->expectException(AuthenticationDenied::class);
        $middleware->process(new ServerRequest(), $handler);
    }

    public function testCommandMappingRequiresTheAuthenticatedRefreshCredential(): void
    {
        $fixture   = IdentityFixture::create();
        $container = $fixture->container();
        $input     = $container->get(IdentityHttpInput::class);
        $this->expectException(AuthenticationDenied::class);
        $input->command(IdentityScenario::id(), 'refresh', Realm::USER, new ServerRequest());
    }

    public function testCookieDispatchCannotReturnAnIncompleteSessionResult(): void
    {
        $commands = $this->createMock(CommandBus::class);
        $queries  = $this->createMock(QueryBus::class);
        $commands->expects(self::once())->method('handle');
        $queries->expects(self::once())->method('handle')->willReturn(new IdentityOperationResult(200, ['accessToken' => 'incomplete']));
        $command = $this->createMock(IdentityCommand::class);
        $command->expects(self::once())->method('operationId')->willReturn(IdentityScenario::id());
        $dispatch = new SessionHttpDispatch(new IdentityOperation($commands, $queries));
        $request  = new ServerRequest()->withAttribute('sessionTransport', 'cookie');
        $this->expectException(RuntimeException::class);
        $dispatch->execute($command, $request, Realm::USER);
    }

    public function testResponseGuardCannotDeliverASessionWithoutItsRefreshCredential(): void
    {
        $fixture   = IdentityFixture::create();
        $container = $fixture->container();
        $sessions  = $container->get(SessionIssuance::class);
        $this->expectException(RuntimeException::class);
        SessionResponseGuard::confirm(new JsonResponse(['session' => []]), Realm::USER, $sessions);
    }
}
