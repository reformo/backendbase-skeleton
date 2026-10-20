<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts\Input;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use InvalidArgumentException;

use function in_array;
use function preg_match;

final readonly class OperatorInput
{
    private IdentityId $target;

    public function __construct(public string $action, string $targetId, public string $operator, public string $invitation, public string $approval)
    {
        if (! in_array($action, ['invite-first-admin', 'approve-first-admin'], true)) {
            throw new InvalidArgumentException('Select a supported identity operator action.');
        }

        self::evidence($operator);
        if ($action === 'invite-first-admin') {
            self::evidence($invitation);
        }

        if ($action !== 'invite-first-admin') {
            self::evidence($approval);
        }

        $this->target = IdentityId::fromString($targetId);
    }

    public function targetId(): string
    {
        $target = $this->target;

        return $target->toString();
    }

    private static function evidence(string $value): void
    {
        if (preg_match('/\A[\x21-\x7e][\x20-\x7e]{0,159}\z/D', $value) !== 1) {
            throw new InvalidArgumentException('Supply a printable evidence reference with at most 160 characters.');
        }
    }
}
