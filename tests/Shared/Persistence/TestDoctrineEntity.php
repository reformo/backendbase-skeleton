<?php

declare(strict_types=1);

namespace Tests\Shared\Persistence;

use Backendbase\Shared\Persistence\DoctrineEntityMethods;
use DateTimeImmutable;

final class TestDoctrineEntity
{
    use DoctrineEntityMethods;

    private int $id      = 42;
    private string $name = 'Entity';
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $deletedAt;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable('2026-08-25T10:00:00+00:00');
        $this->deletedAt = new DateTimeImmutable('2026-08-26T10:00:00+00:00');
    }
}
