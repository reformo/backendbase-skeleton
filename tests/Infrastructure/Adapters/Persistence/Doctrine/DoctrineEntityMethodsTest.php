<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Persistence\Doctrine;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DoctrineEntityMethodsTest extends TestCase
{
    #[Test]
    public function itSerializesEntityPropertiesAndExclusions(): void
    {
        $entity = new TestDoctrineEntity();

        self::assertSame([
            'name' => 'Entity',
            'createdAt' => '2026-08-25T10:00:00+00:00',
        ], $entity->toArray());
        self::assertSame([
            'id' => 42,
            'name' => 'Entity',
            'createdAt' => '2026-08-25T10:00:00+00:00',
            'deletedAt' => '2026-08-26T10:00:00+00:00',
        ], $entity->toArray(null, true, true));
        self::assertSame(['createdAt' => '2026-08-25T10:00:00+00:00'], $entity->toArray(['name']));
    }
}
