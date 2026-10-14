<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Domain\Identity;

use Backendbase\Shared\Exception\InvalidUserInput;
use DateTimeImmutable;
use Ramsey\Uuid\Uuid;

use function count;
use function explode;
use function in_array;
use function preg_match;
use function strlen;
use function trim;

final class SystemAdmin extends Actor
{
    public function realm(): Realm
    {
        return Realm::ADMIN;
    }

    public static function invite(IdentityId $id, IdentityId $accountId, LoginName $loginName, string $invitationEvidence, DateTimeImmutable $now): self
    {
        self::evidence($invitationEvidence);

        return new self($id, ['accountId' => $accountId->toString(), 'loginName' => $loginName->toString(), 'approved' => false, 'invitation' => $invitationEvidence, 'approval' => null, 'deletedAt' => null, 'revision' => 1, 'createdAt' => $now->format('Y-m-d H:i:s.u')]);
    }

    public function approve(string $approvalEvidence): void
    {
        self::evidence($approvalEvidence);
        $state = $this->snapshot();
        if ($state['invitation'] === null || self::reference($state['invitation']) === self::reference($approvalEvidence)) {
            throw InvalidUserInput::create('Separate Admin invitation and approval evidence is required.');
        }

        $this->approveWith($approvalEvidence);
    }

    private static function evidence(string $value): void
    {
        if (trim($value) === '' || strlen($value) > 255 || preg_match('/[\x00-\x1f\x7f]/', $value) === 1) {
            throw InvalidUserInput::create('A bounded Admin authorization evidence reference is required.');
        }
    }

    private static function reference(string $evidence): string
    {
        $parts = explode(':', $evidence, 3);

        return count($parts) === 3 && in_array($parts[0], ['invite', 'approve'], true) && Uuid::isValid($parts[1]) ? $parts[2] : $evidence;
    }
}
