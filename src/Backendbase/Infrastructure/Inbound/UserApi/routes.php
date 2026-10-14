<?php

declare(strict_types=1);

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Infrastructure\Inbound\IdentityApi\IdentityRoutes;
use Slim\App;

return static function (App $app): void {
    IdentityRoutes::register($app, Realm::USER);
};
