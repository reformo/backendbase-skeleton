<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts\ReadModel;

use DateTimeImmutable;

final readonly class AccountListItem
{
    /**
     * @var array{
     *     uuid: string,
     *     email: string,
     *     privilegeSlugs: list<string>,
     *     createdAt: DateTimeImmutable
     * }
     */
    private array $data;

    /** @param list<string> $privilegeSlugs */
    public function __construct(string $uuid, string $email, array $privilegeSlugs, DateTimeImmutable $createdAt)
    {
        $this->data = [
            'uuid' => $uuid,
            'email' => $email,
            'privilegeSlugs' => $privilegeSlugs,
            'createdAt' => $createdAt,
        ];
    }

    public function uuid(): string
    {
        return $this->data['uuid'];
    }

    public function email(): string
    {
        return $this->data['email'];
    }

    /** @return list<string> */
    public function privilegeSlugs(): array
    {
        return $this->data['privilegeSlugs'];
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->data['createdAt'];
    }
}
