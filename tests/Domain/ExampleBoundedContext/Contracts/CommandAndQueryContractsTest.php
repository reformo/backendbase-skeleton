<?php

declare(strict_types=1);

namespace Tests\Domain\ExampleBoundedContext\Contracts;

use Backendbase\Domain\ExampleBoundedContext\Contracts\Command\AddNewExample;
use Backendbase\Domain\ExampleBoundedContext\Contracts\Command\ChangeExample;
use Backendbase\Domain\ExampleBoundedContext\Contracts\Command\RemoveExample;
use Backendbase\Domain\ExampleBoundedContext\Contracts\Query\GetExampleByCriteria;
use Backendbase\Domain\ExampleBoundedContext\Contracts\Query\GetExampleGroupsByType;
use Backendbase\Domain\ExampleBoundedContext\Contracts\Query\GetExampleIdByCriteria;
use Backendbase\Domain\ExampleBoundedContext\Contracts\Query\GetExamplesByGroup;
use Backendbase\Domain\ExampleBoundedContext\Domain\ExampleIdentity;
use Backendbase\Domain\ExampleBoundedContext\Domain\ExampleType;
use Backendbase\Shared\Primitives\Pagination;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CommandAndQueryContractsTest extends TestCase
{
    #[Test]
    public function itSerializesExampleCommands(): void
    {
        $add = new AddNewExample(
            'example-id',
            ExampleType::USER,
            42,
            'settings',
            true,
            'theme',
            'dark',
            null,
        );
        self::assertSame([], $add->details());
        self::assertSame($add->toArray(), $add->jsonSerialize());

        $identity = new ExampleIdentity(ExampleType::USER, 42, 'settings', 'theme');
        $change   = new ChangeExample($identity);
        self::assertSame($identity, $change->identity());
        self::assertNull($change->isActive());
        self::assertNull($change->value());
        self::assertNull($change->details());
        self::assertSame($change, $change->setIsActive(false));
        self::assertSame($change, $change->setValue('light'));
        self::assertSame($change, $change->setDetails(['contrast' => 'high']));
        self::assertSame([
            'identity' => [
                'type' => 'user',
                'typeTargetId' => 42,
                'group' => 'settings',
                'key' => 'theme',
            ],
            'isActive' => false,
            'value' => 'light',
            'details' => ['contrast' => 'high'],
        ], $change->jsonSerialize());

        $remove = new RemoveExample($identity);
        self::assertSame($identity, $remove->identity());
        self::assertSame(['identity' => $identity->toArray()], $remove->toArray());
        self::assertSame($remove->toArray(), $remove->jsonSerialize());
    }

    #[Test]
    public function itExposesAndSerializesExampleQueries(): void
    {
        $byCriteria = new GetExampleByCriteria(ExampleType::USER, 42, 'settings', 'theme');
        self::assertSame('settings', $byCriteria->group());
        self::assertSame(ExampleType::USER, $byCriteria->type());
        self::assertSame(42, $byCriteria->typeTargetId());
        self::assertSame('theme', $byCriteria->key());
        self::assertSame($byCriteria->toArray(), $byCriteria->jsonSerialize());

        $idByCriteria = new GetExampleIdByCriteria(ExampleType::USER, 42, 'settings', 'theme');
        self::assertSame('settings', $idByCriteria->group());
        self::assertSame(ExampleType::USER, $idByCriteria->type());
        self::assertSame(42, $idByCriteria->typeTargetId());
        self::assertSame('theme', $idByCriteria->key());
        self::assertSame($idByCriteria->toArray(), $idByCriteria->jsonSerialize());

        $groups = new GetExampleGroupsByType(ExampleType::SYSTEM, null);
        self::assertSame(ExampleType::SYSTEM, $groups->type());
        self::assertNull($groups->typeTargetId());
        self::assertSame([
            'type' => 'system',
            'typeTargetId' => null,
        ], $groups->jsonSerialize());

        $pagination = new Pagination(25, 2);
        $byGroup    = new GetExamplesByGroup(ExampleType::USER, 42, 'settings', $pagination);
        self::assertSame(ExampleType::USER, $byGroup->type());
        self::assertSame(42, $byGroup->typeTargetId());
        self::assertSame('settings', $byGroup->group());
        self::assertSame($pagination, $byGroup->pagination());
        self::assertSame($byGroup->toArray(), $byGroup->jsonSerialize());
    }
}
