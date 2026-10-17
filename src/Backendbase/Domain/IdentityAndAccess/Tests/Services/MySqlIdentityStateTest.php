<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Services;

use Backendbase\Domain\IdentityAndAccess\Tests\Adapters\Persistence\State\StateExamples;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\Services\IsolatedMySql;

use function getenv;

final class MySqlIdentityStateTest extends TestCase
{
    #[DataProvider('records')]
    public function testJsonStateRoundTripAndReplacementUseTypedValues(string $kind): void
    {
        if (getenv('BACKENDBASE_SERVICE_TESTS') !== '1') {
            self::markTestSkipped('An explicitly prepared MySQL 8.4 target is required.');
        }

        $fixture = IsolatedMySql::create();
        try {
            $manager = $fixture->manager();
            $record  = StateExamples::record($kind);
            $before  = $record->state();
            $manager->persist($record);
            $manager->flush();
            $id    = $record->id();
            $class = $record::class;
            $manager->clear();
            $loaded = $manager->find($class, $id);
            self::assertNotNull($loaded);
            $state = $loaded->state();
            self::assertEquals($before, $state);
            $values = $state->toArray();
            $key    = match ($kind) {
                'account' => 'enabled',
                'contact' => 'deliveryState',
                default => 'approved',
            };
            $replacement = $state::fromArray([...$values,
                $key => match ($kind) {
                'contact' => 'accepted', 'admin' => true, default => false
                },
            ]);
            $loaded->replaceState($replacement);
            $manager->flush();
            $manager->clear();
            $reloaded = $manager->find($class, $id);
            self::assertNotNull($reloaded);
            self::assertEquals($replacement, $reloaded->state());
        } finally {
            $fixture->destroy();
        }
    }

    /** @return iterable<string, array{string}> */
    public static function records(): iterable
    {
        yield 'Account' => ['account'];
        yield 'User' => ['user'];
        yield 'Admin' => ['admin'];
        yield 'Contact' => ['contact'];
    }
}
