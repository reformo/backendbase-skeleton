<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\Entity;

use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\IdentityConflict;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidBinaryType;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

#[ORM\MappedSuperclass]
abstract class IdentifiedRecord
{
    #[ORM\Id]
    #[ORM\Column(type: UuidBinaryType::NAME)]
    protected UuidInterface $id;

    #[ORM\Column(type: Types::STRING, length: 36, nullable: true, insertable: false, updatable: false, generated: 'ALWAYS', options: ['fixed' => true, 'charset' => 'ascii', 'collation' => 'ascii_bin'], columnDefinition: 'CHAR(36) CHARACTER SET ascii COLLATE ascii_bin GENERATED ALWAYS AS (BIN_TO_UUID(id, 0)) VIRTUAL')]
    protected string $uuid;

    #[ORM\Version]
    #[ORM\Column(type: Types::INTEGER)]
    protected int $version = 1;

    protected function assignId(string $id): void
    {
        $this->id   = Uuid::fromString($id);
        $this->uuid = $id;
    }

    public function id(): string
    {
        $id = $this->id;

        return $id->toString();
    }

    public function assertRevision(int $expected): void
    {
        if ($expected !== $this->version) {
            throw IdentityConflict::create('The identity record changed. Reload the record before another attempt.');
        }
    }
}
