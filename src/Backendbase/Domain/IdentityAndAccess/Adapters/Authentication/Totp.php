<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Authentication;

use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use DateTimeImmutable;

use function hash_equals;
use function hash_hmac;
use function intdiv;
use function intval;
use function ord;
use function pack;
use function preg_match;
use function random_bytes;
use function sprintf;
use function str_pad;
use function str_split;
use function strpos;
use function substr;

use const STR_PAD_LEFT;

final class Totp
{
    public static function secret(): string
    {
        $bytes = random_bytes(20);
        $bits  = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= sprintf('%08b', ord($byte));
        }

        $secret = '';
        foreach (str_split($bits, 5) as $part) {
            $secret .= substr('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567', intval($part, 2), 1);
        }

        return $secret;
    }

    public static function step(string $secret, string $code, int $lastStep, DateTimeImmutable $now): int|null
    {
        if (preg_match('/\A[0-9]{6}\z/D', $code) !== 1) {
            return null;
        }

        $counter = intdiv($now->getTimestamp(), 30);
        foreach ([$counter - 1, $counter, $counter + 1] as $step) {
            if ($step > $lastStep && hash_equals(self::code($secret, $step), $code)) {
                return $step;
            }
        }

        return null;
    }

    public static function code(string $secret, int $step): string
    {
        $key    = self::decode($secret);
        $digest = hash_hmac('sha1', pack('N2', intdiv($step, 4294967296), $step % 4294967296), $key, true);
        $offset = ord($digest[19]) & 15;
        $number = (((ord($digest[$offset]) & 127) * 16777216) + (ord($digest[$offset + 1]) * 65536) + (ord($digest[$offset + 2]) * 256) + ord($digest[$offset + 3])) % 1000000;

        return str_pad((string) $number, 6, '0', STR_PAD_LEFT);
    }

    private static function decode(string $secret): string
    {
        if (preg_match('/\A[A-Z2-7]{32}\z/D', $secret) !== 1) {
            throw AuthenticationDenied::create('The authentication factor is invalid.');
        }

        $bits = '';
        foreach (str_split($secret) as $character) {
            $position = strpos('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567', $character);
            $bits    .= sprintf('%05b', $position);
        }

        $key = '';
        foreach (str_split($bits, 8) as $byte) {
            $key .= pack('C', intval($byte, 2));
        }

        return $key;
    }
}
