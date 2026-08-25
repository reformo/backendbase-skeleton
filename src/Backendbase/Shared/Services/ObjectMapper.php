<?php

declare(strict_types=1);

namespace Backendbase\Shared\Services;

use Backendbase\Shared\Exception\InvalidUserInput;
use Backendbase\Utility\Arrays\PayloadSanitizer;
use CuyZ\Valinor\Cache\Cache;
use CuyZ\Valinor\Mapper\MappingError;
use CuyZ\Valinor\Mapper\Source\Source;
use CuyZ\Valinor\MapperBuilder;
use ReflectionClass;

use function array_flip;
use function array_intersect_key;
use function array_keys;
use function assert;
use function get_class_vars;

readonly class ObjectMapper
{
    public function __construct(private Cache $cache)
    {
    }

    /**
     * @param class-string<object> $targetFQCN
     * @param array<string, mixed> $payload
     */
    public function map(string $targetFQCN, array $payload, callable|null $afterSanitize = null): object
    {
        $payload = PayloadSanitizer::sanitize($payload);
        if ($afterSanitize !== null) {
            $payload = $afterSanitize($payload);
        }

        $arrayKeysToFilter = array_flip(array_keys(get_class_vars($targetFQCN)));
        $payload           = array_intersect_key($payload, $arrayKeysToFilter);
        try {
            $builder = new MapperBuilder();
            $builder = $builder->withCache($this->cache);

            $mappedObject = $builder
                ->allowPermissiveTypes()
                ->mapper()
                ->map($targetFQCN, Source::array($payload));
            assert($mappedObject::class === $targetFQCN);
        } catch (MappingError $error) {
            $messages      = $error->messages();
            $errorMessages = [];
            $errors        = $messages->errors();
            foreach ($errors as $message) {
                $errorMessages[$message->name()] =  (string) $message;
            }

            throw InvalidUserInput::create(
                'Invalid input(s) provided',
                [
                    'target' => (new ReflectionClass($targetFQCN))->getShortName(),
                    'errors' => $errorMessages,
                ],
            );
        }

        return $mappedObject;
    }
}
