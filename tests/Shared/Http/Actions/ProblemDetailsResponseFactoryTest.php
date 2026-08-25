<?php

declare(strict_types=1);

namespace Tests\Shared\Http\Actions;

use Backendbase\Shared\Exception\CommandFailed;
use Backendbase\Shared\Exception\InvalidUserInput;
use Backendbase\Shared\Services\Translator;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequestFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

use function json_decode;

use const JSON_THROW_ON_ERROR;

final class ProblemDetailsResponseFactoryTest extends TestCase
{
    #[Test]
    public function itTranslatesProblemDetailsMessages(): void
    {
        $translator        = new Translator('en', [
            'en' => ['validation' => ['required' => 'The field is required.']],
        ]);
        $action            = new TestAction($this->createStub(LoggerInterface::class), $translator);
        $action->exception = InvalidUserInput::create('validation.required');

        $response = $this->response($action);
        $payload  = self::payload($response);

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('The field is required.', $payload['message']);
    }

    #[Test]
    public function itPreservesStructuredProblemDetailsMessages(): void
    {
        $translator        = new Translator('en', [
            'en' => ['validation' => ['required' => 'The field is required.']],
        ]);
        $action            = new TestAction($this->createStub(LoggerInterface::class), $translator);
        $action->exception = InvalidUserInput::create('validation');

        $payload = self::payload($this->response($action));

        self::assertSame(['required' => 'The field is required.'], $payload['message']);
    }

    #[Test]
    public function itReplacesMessageParametersAndLogsServerFailures(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with('Invalid email', self::isArray());
        $action            = new TestAction($logger);
        $action->exception = CommandFailed::create('failure', [
            'message' => 'Invalid :field',
            'field' => 'email',
        ]);

        $response = $this->response($action);
        $payload  = self::payload($response);

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('Invalid email', $payload['message']);
    }

    private function response(TestAction $action): ResponseInterface
    {
        return $action(
            (new ServerRequestFactory())->createServerRequest('GET', '/resource'),
            new Response(),
            [],
        );
    }

    /** @return array<string, mixed> */
    private static function payload(ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }
}
