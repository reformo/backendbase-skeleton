<?php

declare(strict_types=1);

namespace Backendbase\Shared\Idempotency;

use RuntimeException;

final class RequestMismatch extends RuntimeException
{
}
