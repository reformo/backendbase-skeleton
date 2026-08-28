<?php

declare(strict_types=1);

use Backendbase\Domain\IdentityAndAccess\Adapters\Authentication\Jwt;
use Backendbase\Domain\IdentityAndAccess\Adapters\Authentication\JwtAuthorizationStore;
use Backendbase\Domain\IdentityAndAccess\Adapters\Authentication\JwtTokenCodec;
use Backendbase\Domain\IdentityAndAccess\Adapters\Authentication\JwtTokenConfiguration;
use Backendbase\Domain\IdentityAndAccess\Contracts\AuthorizationStore;
use Backendbase\Domain\IdentityAndAccess\Contracts\TokenIssuer;
use Backendbase\Domain\IdentityAndAccess\Contracts\TokenValidator;
use Backendbase\Shared\Exception\ResourceNotFound;
use Backendbase\Shared\Primitives\HealthCheckData;
use Backendbase\Shared\Settings;
use DI\ContainerBuilder;
use Lcobucci\Clock\SystemClock;
use Psr\Container\ContainerInterface;
use Redislabs\Module\RedisJson\RedisJson;
use Redislabs\Module\RedisJson\RedisJsonInterface;

return static function (ContainerBuilder $containerBuilder): void {
    $containerBuilder->addDefinitions([
        RedisJsonInterface::class => static function (ContainerInterface $container) {
            $settings        = $container->get(Settings::class);
            $redisConfig     = $settings->get('redis');
            $readinessConfig = $settings->get('readiness');
            $timeoutSeconds  = (float) ($readinessConfig['timeoutSeconds'] ?? 2);
            try {
                $redisClient = new Redis();
                $redisClient->connect(
                    $redisConfig['host'] ?? '127.0.0.1',
                    $redisConfig['port'] ?? 6379,
                    $timeoutSeconds,
                );
                $redisClient->setOption(Redis::OPT_READ_TIMEOUT, $timeoutSeconds);
            } catch (Throwable) {
                $healthStatus = new HealthCheckData();
                $healthStatus->setStatus(503);
                $healthStatus->setStatusString('rcon');

                throw ResourceNotFound::create('rcon', $healthStatus->jsonSerialize());
            }

            return new RedisJson(new \Redislabs\RedisClient\Redis($redisClient));
        },
        JwtTokenConfiguration::class => static function (ContainerInterface $container) {
            $settings = $container->get(Settings::class);

            return new JwtTokenConfiguration($settings->get('jwt'));
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
