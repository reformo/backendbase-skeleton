<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Http\Idempotency;

use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use Backendbase\Shared\Idempotency\RequestIdentity;
use Backendbase\Shared\Idempotency\RequestReplayCipher;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpBadRequestException;
use Slim\Routing\RouteContext;

use function count;
use function hash;
use function json_encode;
use function preg_match;

use const JSON_THROW_ON_ERROR;

final class ReplayInput
{
    public static function key(ServerRequestInterface $request): string
    {
        $values = $request->getHeader('Idempotency-Key');
        if (count($values) !== 1 || preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]{0,254}\z/D', $values[0]) !== 1) {
            throw new HttpBadRequestException($request, 'Supply one Idempotency-Key with 1 to 255 letters, digits, periods, underscores, colons, or hyphens. Start with a letter or digit.');
        }

        return $values[0];
    }

    public static function identity(ServerRequestInterface $request, string $key, RequestReplayCipher $cipher): RequestIdentity
    {
        $context   = RouteContext::fromRequest($request);
        $route     = $context->getRoute();
        $operation = $route?->getName();
        if ($operation === null || $operation === '') {
            throw new LogicException('Idempotent operations require a named route.');
        }

        $scope   = [$operation, self::caller($request), $key];
        $uri     = $request->getUri();
        $body    = $request->getBody();
        $method  = $request->getMethod();
        $path    = $uri->getPath();
        $query   = $uri->getQuery();
        $content = [$method, $path, $query, hash('sha256', (string) $body)];
        foreach (['Content-Type', 'Accept', 'Accept-Language', 'The-Timezone-IANA', 'Authorization', 'Backendbase-Api-Key'] as $header) {
            $content[] = $request->getHeaderLine($header);
        }

        return new RequestIdentity(
            hash('sha256', json_encode($scope, JSON_THROW_ON_ERROR)),
            $cipher->fingerprint(json_encode($content, JSON_THROW_ON_ERROR)),
        );
    }

    /** @return list<string> */
    private static function caller(ServerRequestInterface $request): array
    {
        $principal = $request->getAttribute('identityPrincipal');
        if (! $principal instanceof Principal) {
            return ['anonymous'];
        }

        $identity = $principal->identity();
        $realm    = $principal->realm();

        return [$realm->value, $identity['accountId'], $identity['actorId']];
    }
}
