<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts\Input;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use InvalidArgumentException;

use function in_array;
use function preg_match;

final readonly class OperatorInput
{
    /** @var array{action: string, target: IdentityId, operator: string, invitation: string, approval: string} */
    private array $values;

    public function __construct(string $action, string $targetId, string $operator, string $invitation, string $approval)
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

        $this->values = ['action' => $action, 'target' => IdentityId::fromString($targetId), 'operator' => $operator, 'invitation' => $invitation, 'approval' => $approval];
    }

    /** @return array{action: string, targetId: string, operator: string, invitation: string, approval: string} */
    public function values(): array
    {
        $target = $this->values['target'];

        return ['action' => $this->values['action'], 'targetId' => $target->toString(), 'operator' => $this->values['operator'], 'invitation' => $this->values['invitation'], 'approval' => $this->values['approval']];
    }

    private static function evidence(string $value): void
    {
        if (preg_match('/\A[\x21-\x7e][\x20-\x7e]{0,159}\z/D', $value) !== 1) {
            throw new InvalidArgumentException('Supply a printable evidence reference with at most 160 characters.');
        }
    }
}
