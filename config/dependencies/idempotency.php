<?php

declare(strict_types=1);

use Backendbase\Infrastructure\Adapters\Http\DomainErrorProblemDetailsMapper;
use Backendbase\Infrastructure\Adapters\Http\HttpErrorHandler;
use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\Idempotency\DoctrineRequestReplayStore;
use Backendbase\Infrastructure\Adapters\Security\Idempotency\SodiumRequestReplayCipher;
use Backendbase\Shared\Idempotency\RequestReplayCipher;
use Backendbase\Shared\Idempotency\RequestReplayStore;
use Backendbase\Shared\Services\Translator;
use DI\ContainerBuilder;
use Laminas\Diactoros\ResponseFactory;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Slim\CallableResolver;

use function DI\autowire;

return static function (ContainerBuilder $builder): void {
    $builder->addDefinitions([
        RequestReplayStore::class => autowire(DoctrineRequestReplayStore::class),
        RequestReplayCipher::class => autowire(SodiumRequestReplayCipher::class),
        HttpErrorHandler::class => static function (ContainerInterface $container): HttpErrorHandler {
            $logger     = $container->get(LoggerInterface::class);
            $mapper     = $container->get(DomainErrorProblemDetailsMapper::class);
            $translator = $container->get(Translator::class);

            return new HttpErrorHandler(new CallableResolver($container), new ResponseFactory(), $logger, $mapper, $translator);
        },
    ]);
};
