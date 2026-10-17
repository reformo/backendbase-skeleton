<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Support;

use Backendbase\Domain\IdentityAndAccess\Contracts\VerificationLimiter;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactValue;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationClient;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationLimited;
use Backendbase\Shared\Time\Clock;

use function max;

final class MemoryVerificationLimiter implements VerificationLimiter
{
    /** @var array<string, array{count: int, until: int}> */
    private array $counters = [];

    public function __construct(private Clock $clock)
    {
    }

    public function send(ContactValue $contact, VerificationClient $client): void
    {
        $key       = $contact->counterKey();
        $clientKey = $client->key();
        $this->check('cooldown:' . $key, 1);
        $this->check('send:' . $key, 5);
        $this->check('ip:' . $clientKey, 20);
        $this->increment('cooldown:' . $key, 60);
        $this->increment('send:' . $key, 3600);
        $this->increment('ip:' . $clientKey, 3600);
    }

    public function attempt(ContactValue $contact): void
    {
        $key = 'failure:' . $contact->counterKey();
        $this->check($key, 10);
        $this->increment($key, 3600);
    }

    public function accept(ContactValue $contact): void
    {
        $key = 'failure:' . $contact->counterKey();
        if (! isset($this->counters[$key])) {
            return;
        }

        $this->counters[$key]['count'] = max(0, $this->counters[$key]['count'] - 1);
    }

    private function check(string $key, int $limit): void
    {
        $clock   = $this->clock;
        $now     = $clock->now();
        $seconds = $now->getTimestamp();
        $counter = $this->counters[$key] ?? ['count' => 0, 'until' => 0];
        if ($counter['until'] > $seconds && $counter['count'] >= $limit) {
            throw VerificationLimited::create('The verification request limit was reached.', ['retryAfter' => $counter['until'] - $seconds]);
        }
    }

    private function increment(string $key, int $duration): void
    {
        $clock                = $this->clock;
        $now                  = $clock->now();
        $seconds              = $now->getTimestamp();
        $counter              = $this->counters[$key] ?? ['count' => 0, 'until' => 0];
        $this->counters[$key] = $counter['until'] <= $seconds ? ['count' => 1, 'until' => $seconds + $duration] : ['count' => $counter['count'] + 1, 'until' => $counter['until']];
    }
}
