<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleBoundedContext\Tests\Adapters\Persistence\Doctrine;

use Backendbase\Domain\ExampleBoundedContext\Adapters\Persistence\Doctrine\ExampleReadModelMapper;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

use function array_replace;

final class ExampleReadModelMapperTest extends TestCase
{
    #[Test]
    public function itMapsPersistedScalarRepresentations(): void
    {
        self::assertSame(42, ExampleReadModelMapper::integer('42', 'value'));
        self::assertSame(42, ExampleReadModelMapper::integer(42, 'value'));
    }

    #[Test]
    public function itRejectsInvalidPersistedRepresentations(): void
    {
        $valid = self::validRow();
        $cases = [
            array_replace($valid, ['type' => 'invalid']),
            array_replace($valid, ['uuid' => 42]),
            array_replace($valid, ['type_target_id' => 0]),
            array_replace($valid, ['is_active' => 2]),
            array_replace($valid, ['details' => []]),
            array_replace($valid, ['details' => '{']),
            array_replace($valid, ['details' => '1']),
            array_replace($valid, ['details' => '["value"]']),
            array_replace($valid, ['updated_at' => 'not-a-date']),
        ];

        foreach ($cases as $row) {
            try {
                ExampleReadModelMapper::details($row);
                self::fail('The invalid persisted row must fail.');
            } catch (UnexpectedValueException) {
                self::addToAssertionCount(1);
            }
        }

        $this->expectException(UnexpectedValueException::class);
        ExampleReadModelMapper::integer([], 'value');
    }

    /** @return array<string, mixed> */
    private static function validRow(): array
    {
        return [
            'uuid' => 'example-id',
            'type' => 'system',
            'type_target_id' => 42,
            'lookup_group' => 'settings',
            'lookup_key' => 'page-size',
            'lookup_value' => '25',
            'details' => '{}',
            'is_active' => 1,
            'updated_at' => '2026-08-25T10:00:00+00:00',
            'created_at' => '2026-08-25T10:00:00+00:00',
        ];
    }
}
