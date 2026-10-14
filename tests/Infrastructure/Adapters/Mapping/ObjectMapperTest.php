<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Mapping;

use Backendbase\Infrastructure\Adapters\Mapping\ObjectMapper;
use Backendbase\Shared\Exception\InvalidUserInput;
use CuyZ\Valinor\Cache\FileSystemCache;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function sys_get_temp_dir;

final class ObjectMapperTest extends TestCase
{
    /** @var FileSystemCache<mixed> */
    private FileSystemCache $cache;

    protected function setUp(): void
    {
        $this->cache = new FileSystemCache(sys_get_temp_dir() . '/backendbase-valinor-test');
    }

    protected function tearDown(): void
    {
        $this->cache->clear();
    }

    #[Test]
    public function itMapsTypedPayloadsWithExplicitNormalization(): void
    {
        $mapper = new ObjectMapper($this->cache);
        $mapped = $mapper->map(
            MappedInput::class,
            ['name' => 'User', 'age' => 42],
            static function (array $payload): array {
                $payload['name'] = 'Mapped ' . $payload['name'];

                return $payload;
            },
        );

        self::assertInstanceOf(MappedInput::class, $mapped);
        self::assertSame('Mapped User', $mapped->name);
        self::assertSame(42, $mapped->age);
    }

    /** @param array<string, mixed> $payload */
    #[Test]
    #[DataProvider('invalidPayloads')]
    public function itRejectsUnknownFieldsAndInvalidScalarTypes(array $payload): void
    {
        $mapper = new ObjectMapper($this->cache);

        $this->expectException(InvalidUserInput::class);

        $mapper->map(MappedInput::class, $payload);
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function invalidPayloads(): array
    {
        return [
            'unknown field' => [['name' => 'User', 'age' => 42, 'ignored' => true]],
            'numeric string' => [['name' => 'User', 'age' => '42']],
            'floating point' => [['name' => 'User', 'age' => 42.0]],
            'boolean' => [['name' => 'User', 'age' => true]],
            'null' => [['name' => 'User', 'age' => null]],
            'missing' => [['name' => 'User']],
            'nonstring name' => [['name' => 123, 'age' => 42]],
        ];
    }

    #[Test]
    public function itKeepsTextUnchangedWithoutAnExplicitNormalizer(): void
    {
        $mapper = new ObjectMapper($this->cache);
        $mapped = $mapper->map(MappedInput::class, ['name' => '  <b>User</b>  ', 'age' => 42]);

        self::assertInstanceOf(MappedInput::class, $mapped);
        self::assertSame('  <b>User</b>  ', $mapped->name);
    }

    #[Test]
    public function itAllowsAnExplicitContractSpecificFieldException(): void
    {
        $mapper = new ObjectMapper($this->cache);
        $mapped = $mapper->map(
            MappedInput::class,
            ['name' => 'User', 'age' => 42, 'legacyField' => true],
            static function (array $payload): array {
                unset($payload['legacyField']);

                return $payload;
            },
        );

        self::assertInstanceOf(MappedInput::class, $mapped);
        self::assertSame('User', $mapped->name);
    }

    #[Test]
    public function itReportsObjectMappingErrorsAsInvalidInput(): void
    {
        $mapper = new ObjectMapper($this->cache);

        try {
            $mapper->map(MappedInput::class, ['name' => 'User', 'age' => []]);
            self::fail('Invalid mapped input must fail.');
        } catch (InvalidUserInput $exception) {
            self::assertSame('Invalid input(s) provided', $exception->getMessage());
            self::assertSame('MappedInput', $exception->context()['target']);
            self::assertNotEmpty($exception->context()['errors']);
            self::assertSame(
                'The value or field does not match the input contract.',
                $exception->context()['errors']['age'],
            );
        }
    }
}
