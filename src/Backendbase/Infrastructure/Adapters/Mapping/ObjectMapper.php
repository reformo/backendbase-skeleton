<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Mapping;

use Backendbase\Shared\Exception\InvalidUserInput;
use CuyZ\Valinor\Cache\Cache;
use CuyZ\Valinor\Mapper\MappingError;
use CuyZ\Valinor\Mapper\Source\Source;
use CuyZ\Valinor\MapperBuilder;
use ReflectionClass;

use function assert;

readonly class ObjectMapper
{
    public function __construct(private Cache $cache)
    {
    }

    /**
     * @param class-string<T>      $targetClass
     * @param array<string, mixed> $payload
     *
     * @return T
     *
     * @template T of object
     */
    public function map(string $targetClass, array $payload, callable|null $normalizer = null): object
    {
        if ($normalizer !== null) {
            $payload = $normalizer($payload);
        }

        try {
            $builder      = new MapperBuilder();
            $builder      = $builder->withCache($this->cache);
            $mapper       = $builder->mapper();
            $mappedObject = $mapper->map($targetClass, Source::array($payload));
            assert($mappedObject::class === $targetClass);
        } catch (MappingError $error) {
            $messages      = $error->messages();
            $errorMessages = [];
            $errors        = $messages->errors();
            foreach ($errors as $message) {
                $errorMessages[$message->name()] = 'The value or field does not match the input contract.';
            }

            throw InvalidUserInput::create(
                'Invalid input(s) provided',
                [
                    'target' => (new ReflectionClass($targetClass))->getShortName(),
                    'errors' => $errorMessages,
                ],
            );
        }

        return $mappedObject;
    }
}
