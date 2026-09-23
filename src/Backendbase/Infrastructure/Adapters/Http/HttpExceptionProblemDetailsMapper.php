<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Http;

use Backendbase\Infrastructure\Adapters\Http\Actions\ActionError;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpException;
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Exception\HttpNotImplementedException;
use Slim\Exception\HttpUnauthorizedException;

final class HttpExceptionProblemDetailsMapper
{
    public static function map(HttpException $exception, ActionError $error): void
    {
        if ($exception instanceof HttpNotFoundException) {
            $error->setCode('http/resource-not-found');
            $error->setType(ActionError::RESOURCE_NOT_FOUND);

            return;
        }

        if ($exception instanceof HttpMethodNotAllowedException) {
            $error->setCode('http/not-allowed');
            $error->setType(ActionError::NOT_ALLOWED);

            return;
        }

        if ($exception instanceof HttpUnauthorizedException) {
            $error->setCode('http/unauthenticated');
            $error->setType(ActionError::UNAUTHENTICATED);

            return;
        }

        if ($exception instanceof HttpForbiddenException) {
            $error->setCode('http/insufficient-privileges');
            $error->setType(ActionError::INSUFFICIENT_PRIVILEGES);

            return;
        }

        if ($exception instanceof HttpBadRequestException) {
            $error->setCode('http/bad-request');
            $error->setType(ActionError::BAD_REQUEST);

            return;
        }

        if (! ($exception instanceof HttpNotImplementedException)) {
            return;
        }

        $error->setCode('http/not-implemented');
        $error->setType(ActionError::NOT_IMPLEMENTED);
    }
}
