<?php

declare(strict_types=1);

namespace Backendbase\Shared\Http\Actions;

use Backendbase\Shared\ProblemDetailsException;
use Backendbase\Shared\Services\Translator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpBadRequestException;

use function file_get_contents;
use function is_array;
use function is_object;
use function json_decode;
use function json_encode;
use function json_last_error;

use const JSON_ERROR_NONE;
use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;

abstract class Action
{
    protected Request $request;

    protected Response $response;
    /** @var array<string, mixed> */
    protected array $args;

    public function __construct(protected LoggerInterface $logger, protected Translator|null $translator = null)
    {
    }

    /**
     * @param array<string, mixed> $args
     *
     * @throws HttpBadRequestException
     */
    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $this->request  = $request;
        $this->response = $response;
        $this->args     = $args;
        try {
            return $this->action();
        } catch (ProblemDetailsException $exception) {
            return ProblemDetailsResponseFactory::create($exception, $this->logger, $this->translator);
        }
    }

    /**
     * @throws ProblemDetailsException
     * @throws HttpBadRequestException
     */
    abstract protected function action(): Response;

    /**
     * @return array<array-key, mixed>|object
     *
     * @throws HttpBadRequestException
     */
    protected function getFormData(): array|object
    {
        $requestBody = file_get_contents('php://input');
        if ($requestBody === false) {
            throw new HttpBadRequestException($this->request, 'Could not read JSON input.');
        }

        $input = json_decode($requestBody);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new HttpBadRequestException($this->request, 'Malformed JSON input.');
        }

        if (! is_array($input) && ! is_object($input)) {
            throw new HttpBadRequestException($this->request, 'JSON input must be an object or an array.');
        }

        return $input;
    }

    /** @throws HttpBadRequestException */
    protected function resolveArg(string $name): mixed
    {
        if (! isset($this->args[$name])) {
            throw new HttpBadRequestException($this->request, 'Could not resolve argument `' . $name . '`.');
        }

        return $this->args[$name];
    }

    /** @param array<array-key, mixed>|object|null $data */
    protected function respondWithData(array|object|null $data = null, int $statusCode = 200): Response
    {
        $payload = new ActionPayload($statusCode, $data);

        return $this->respond($payload);
    }

    protected function respond(ActionPayload $payload): Response
    {
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        $this->response->getBody()->write($json);

        return $this->response
                    ->withHeader('Content-Type', 'application/json')
                    ->withStatus($payload->getStatusCode());
    }
}
