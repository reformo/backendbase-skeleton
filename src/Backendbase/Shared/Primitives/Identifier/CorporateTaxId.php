<?php

declare(strict_types=1);

namespace Backendbase\Shared\Primitives\Identifier;

use Backendbase\Shared\Exception\InvalidUserInput;
use Override;

use function array_map;
use function count;
use function str_split;
use function strlen;

class CorporateTaxId implements TaxId
{
    public function __construct(private readonly string $taxId)
    {
        self::validateTaxId($taxId);
    }

    #[Override]
    public function taxId(): string
    {
        return $this->taxId;
    }

    private static function validateTaxId(string $taxId): void
    {
        if (strlen($taxId) !== 10) {
            throw InvalidUserInput::create('Corporate tax id length must be 10: ' . $taxId);
        }

        $digits = str_split($taxId, 1);
        $digits = array_map(self::parseInt(...), $digits);
        $sum    = 0;
        for ($i = 0; $i < count($digits) - 1; $i++) {
            $temp = ($digits[$i] + 10 - ($i + 1)) % 10;
            $incr = ($temp === 9 ? 9 : ($temp * 2 ** (10 - ($i + 1))) % 9);
            $sum += $incr;
        }

        $checksum = (10 - ($sum % 10)) % 10;
        if ($checksum !== $digits[9]) {
            throw InvalidUserInput::create('Corporate tax id checksum for 10th number failed: ' . $taxId);
        }
    }

    private static function parseInt(string $item): int
    {
        return (int) $item;
    }
}
