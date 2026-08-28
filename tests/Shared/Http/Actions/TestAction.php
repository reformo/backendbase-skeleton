<?php

declare(strict_types=1);

namespace Tests\Shared\Http\Actions;

use Backendbase\Shared\Http\Actions\Action;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class TestAction extends Action
{
    public bool $missingArgument = false;

    /** @return array<array-key, mixed>|object */
    public function formData(ServerRequestInterface $request): array|object
    {
        $this->request = $request;

        return $this->getFormData();
    }

    protected function action(): ResponseInterface
    {
        $argument = $this->resolveArg($this->missingArgument ? 'missing' : 'resourceId');

        return $this->respondWithData(['resourceId' => $argument], 201);
    }
}
