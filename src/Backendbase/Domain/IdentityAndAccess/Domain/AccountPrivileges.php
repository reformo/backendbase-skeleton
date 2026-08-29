<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Domain;

use function array_unique;
use function array_values;

final readonly class AccountPrivileges
{
    /** @var list<string> */
    private array $slugs;

    /** @param list<string> $slugs */
    public function __construct(array $slugs)
    {
        $this->slugs = array_values(array_unique($slugs));
    }

    /** @return list<string> */
    public function slugs(): array
    {
        return $this->slugs;
    }
}
