<?php

declare(strict_types=1);

namespace Backendbase\Shared\CQRS;

use JsonSerializable;

/** @template-covariant TResult */
interface Query extends JsonSerializable
{
}
