<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Mapping;

use Backendbase\Infrastructure\Adapters\Mapping\ObjectMapper;
use Backendbase\Shared\Exception\InvalidUserInput;
use CuyZ\Valinor\Cache\FileSystemCache;
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
    public function itMapsSanitizedPayloadsToObjects(): void
    {
        $mapper = new ObjectMapper($this->cache);
        $mapped = $mapper->map(
            MappedInput::class,
            ['name' => 'User', 'age' => 42, 'ignored' => true],
            static function (array $payload): array {
                $payload['name'] = 'Mapped ' . $payload['name'];

                return $payload;
            },
        );

        self::assertInstanceOf(MappedInput::class, $mapped);
        self::assertSame('Mapped User', $mapped->name);
        self::assertSame(42, $mapped->age);
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
        }
    }
}
