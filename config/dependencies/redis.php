<?php

declare(strict_types=1);

use Backendbase\Domain\IdentityAndAccess\Adapters\Authentication\Jwt;
use Backendbase\Domain\IdentityAndAccess\Adapters\Authentication\JwtAuthorizationStore;
use Backendbase\Domain\IdentityAndAccess\Adapters\Authentication\JwtTokenCodec;
use Backendbase\Domain\IdentityAndAccess\Adapters\Authentication\JwtTokenConfiguration;
use Backendbase\Domain\IdentityAndAccess\Contracts\AuthorizationStore;
use Backendbase\Domain\IdentityAndAccess\Contracts\TokenIssuer;
use Backendbase\Domain\IdentityAndAccess\Contracts\TokenValidator;
use Backendbase\Infrastructure\Configuration\RedisSettings;
use Backendbase\Shared\Configuration\JwtSettings;
use Backendbase\Shared\Exception\ResourceNotFound;
use Backendbase\Shared\Primitives\HealthCheckData;
use DI\ContainerBuilder;
use Lcobucci\Clock\SystemClock;
use Psr\Container\ContainerInterface;
use Redislabs\Module\RedisJson\RedisJson;
use Redislabs\Module\RedisJson\RedisJsonInterface;

return static function (ContainerBuilder $containerBuilder): void {
    $containerBuilder->addDefinitions([
        RedisJsonInterface::class => static function (ContainerInterface $container) {
            $redisSettings = $container->get(RedisSettings::class);
            try {
                $redisClient = new Redis();
                $host        = $redisSettings->host();
                $port        = $redisSettings->port();
                $timeout     = $redisSettings->readinessTimeoutSeconds();
                $redisClient->connect(
                    $host,
                    $port,
                    $timeout,
                );
                $redisClient->setOption(Redis::OPT_READ_TIMEOUT, $timeout);
            } catch (Throwable) {
                $healthStatus = new HealthCheckData();
                $healthStatus->setStatus(503);
                $healthStatus->setStatusString('rcon');

                throw ResourceNotFound::create('rcon', $healthStatus->jsonSerialize());
            }

            return new RedisJson(new \Redislabs\RedisClient\Redis($redisClient));
        },
        JwtTokenConfiguration::class => static function (ContainerInterface $container) {
            return new JwtTokenConfiguration($container->get(JwtSettings::class));
        },
        JwtTokenCodec::class => static fn (ContainerInterface $container) => new JwtTokenCodec(
            $container->get(JwtTokenConfiguration::class),
            SystemClock::fromUTC(),
        ),
        AuthorizationStore::class => static fn (ContainerInterface $container) => new JwtAuthorizationStore(
            $container->get(RedisJsonInterface::class),
            $container->get(JwtTokenConfiguration::class),
        ),
        Jwt::class => static fn (ContainerInterface $container) => new Jwt(
            $container->get(JwtTokenCodec::class),
            $container->get(AuthorizationStore::class),
        ),
        TokenIssuer::class => static fn (ContainerInterface $container) => $container->get(Jwt::class),
        TokenValidator::class => static fn (ContainerInterface $container) => $container->get(Jwt::class),
    ]);
};
