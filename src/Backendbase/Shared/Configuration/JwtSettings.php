<?php

declare(strict_types=1);

namespace Backendbase\Shared\Configuration;

use Backendbase\Shared\Settings;
use DateInterval;

/** @phpstan-import-type JwtValues from ValidatedApplicationSettings */
final readonly class JwtSettings
{
    /** @var JwtValues */
    private array $values;

    public function __construct(Settings $settings)
    {
        $this->values = ValidatedApplicationSettings::jwt($settings->get('jwt'));
    }

    public function alias(): string
    {
        return $this->values['alias'];
    }

    /** @return non-empty-string */
    public function issuer(): string
    {
        return $this->values['issuer'];
    }

    /** @return non-empty-string */
    public function permittedFor(): string
    {
        return $this->values['permitted-for'];
    }

    /** @return non-empty-string */
    public function signingKey(): string
    {
        return $this->values['sign-key'];
    }

    public function duration(): DateInterval
    {
        return new DateInterval($this->values['duration']);
    }
}
