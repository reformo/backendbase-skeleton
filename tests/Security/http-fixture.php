<?php

declare(strict_types=1);

use Tests\Security\Http\IdentityHttpEnvironment;

require getcwd() . '/vendor/autoload.php';

$action = $argv[1] ?? '';
if ($action === 'destroy') {
    IdentityHttpEnvironment::destroy($argv[2] ?? '');
    echo "null\n";
    exit(0);
}

$result = match ($action) {
    'create' => IdentityHttpEnvironment::create(),
    'seed' => IdentityHttpEnvironment::seed(),
    default => throw new RuntimeException('Unknown HTTP fixture action.'),
};
echo json_encode($result, JSON_THROW_ON_ERROR) . PHP_EOL;
