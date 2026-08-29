<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Domain;

use InvalidArgumentException;

use function array_is_list;
use function array_unique;
use function count;
use function is_string;
use function mb_strlen;
use function sort;

final readonly class AccountPrivileges
{
    public const int MAX_COUNT = 100;

    public const int MAX_SLUG_LENGTH = 100;

    /** @var list<string> */
    private array $slugs;

    /** @param array<array-key, mixed> $slugs */
    public function __construct(array $slugs)
    {
        if (! array_is_list($slugs) || count($slugs) > self::MAX_COUNT) {
            throw new InvalidArgumentException('The account privilege list is invalid.');
        }

        $validatedSlugs = [];
        foreach ($slugs as $slug) {
            if (! is_string($slug) || $slug === '' || mb_strlen($slug) > self::MAX_SLUG_LENGTH) {
                throw new InvalidArgumentException('An account privilege slug is invalid.');
            }

            $validatedSlugs[] = $slug;
        }

        if (count(array_unique($validatedSlugs)) !== count($validatedSlugs)) {
            throw new InvalidArgumentException('The account privilege list contains duplicates.');
        }

        sort($validatedSlugs);
        $this->slugs = $validatedSlugs;
    }

    /** @return list<string> */
    public function slugs(): array
    {
        return $this->slugs;
    }
}
