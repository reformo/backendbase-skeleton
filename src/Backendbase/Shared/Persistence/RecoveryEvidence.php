<?php

declare(strict_types=1);

namespace Backendbase\Shared\Persistence;

use InvalidArgumentException;

use function preg_match;

final readonly class RecoveryEvidence
{
    public function __construct(private string $operator, private string $reference)
    {
        foreach ([$operator, $reference] as $value) {
            if (preg_match('/\A[\x21-\x7e][\x20-\x7e]{0,254}\z/D', $value) !== 1) {
                throw new InvalidArgumentException('Operator and provider evidence references must be nonempty printable ASCII, with at most 255 characters.');
            }
        }
    }

    /** @return array{operator_name: string, evidence_reference: string} */
    public function auditFields(): array
    {
        return ['operator_name' => $this->operator, 'evidence_reference' => $this->reference];
    }
}
