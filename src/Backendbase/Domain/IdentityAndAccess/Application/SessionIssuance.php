<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application;

use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\IdentityConflict;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Domain\Primitives\SessionState;
use Backendbase\Domain\IdentityAndAccess\Domain\Sessions\RefreshToken;
use Backendbase\Domain\IdentityAndAccess\Domain\Sessions\Session;
use DateTimeImmutable;
use Throwable;

final readonly class SessionIssuance
{
    public function __construct(private AuthenticationTokens $tokens, private AuthenticationGate $gate)
    {
    }

    public function issue(Principal $principal, DateTimeImmutable $expiresAt, SessionState|null $session = null): string
    {
        $currentGate   = $this->gate;
        $currentTokens = $this->tokens;

        $currentGate->confirm($principal);
        $token = $currentTokens->issue($principal, $expiresAt, $session);
        $this->confirmAfter($principal);

        return $token;
    }

    /** @return array<string, string> */
    public function start(Session $session, RefreshToken $refreshToken, DateTimeImmutable $now): array
    {
        $state     = $session->snapshot();
        $expiresAt = $session->tokenExpiresAt($now);
        $principal = $state->principal;
        $token     = $this->issue($principal, $expiresAt, $state);

        return self::result($session, $refreshToken, $token, $expiresAt);
    }

    public function authenticateRefresh(Realm $realm, RefreshToken $token): Principal
    {
        $tokens    = $this->tokens;
        $gate      = $this->gate;
        $state     = $tokens->session($realm, $token, true);
        $principal = $state->principal;
        $gate->confirm($principal);

        return $state->principal;
    }

    public function confirmResult(Realm $realm, RefreshToken $token): void
    {
        $tokens = $this->tokens;
        $state  = $tokens->session($realm, $token, true);
        $hash   = $token->digest();
        if ($state->refreshHash !== $hash) {
            throw IdentityConflict::create('The renewal result was replaced. Renew with the current refresh credential.');
        }
    }

    /** @return array<string, string> */
    public function renew(Realm $realm, RefreshToken $provided, DateTimeImmutable $now): array
    {
        $tokens    = $this->tokens;
        $gate      = $this->gate;
        $expected  = $tokens->session($realm, $provided);
        $principal = $expected->principal;
        $gate->confirm($principal);
        $refreshToken = RefreshToken::create($expected->principal);
        $session      = new Session($expected);
        $session->rotate($refreshToken, $now);
        $replacement = $session->snapshot();
        $expiresAt   = $session->tokenExpiresAt($now);
        $token       = $tokens->rotate($provided, $replacement, $expiresAt);
        $principal   = $replacement->principal;
        $this->confirmAfter($principal);

        return self::result($session, $refreshToken, $token, $expiresAt);
    }

    private function confirmAfter(Principal $principal): void
    {
        $currentGate = $this->gate;
        try {
            $currentGate->confirm($principal);
        } catch (Throwable $exception) {
            $currentTokens = $this->tokens;
            $currentTokens->revokeSession($principal);

            throw $exception;
        }
    }

    /** @return array<string, string> */
    private static function result(Session $session, RefreshToken $refreshToken, string $token, DateTimeImmutable $expiresAt): array
    {
        $state            = $session->snapshot();
        $idleExpiresAt    = $session->idleExpiresAt();
        $maximumExpiresAt = new DateTimeImmutable('@' . $state->maximumExpiresAt);
        $refreshValue     = $refreshToken->expose();
        $accessExpiry     = $expiresAt->format('Y-m-d\TH:i:s\Z');
        $idleExpiry       = $idleExpiresAt->format('Y-m-d\TH:i:s\Z');
        $maximumExpiry    = $maximumExpiresAt->format('Y-m-d\TH:i:s\Z');

        return [
            'accessToken' => $token,
            'refreshToken' => $refreshValue,
            'tokenType' => 'Bearer',
            'expiresAt' => $accessExpiry,
            'sessionIdleExpiresAt' => $idleExpiry,
            'sessionExpiresAt' => $maximumExpiry,
        ];
    }
}
