<?php

declare(strict_types=1);

return [
    'idempotency' => ['key' => backendbaseEnv('BACKENDBASE_IDEMPOTENCY_KEY', '')],
];
