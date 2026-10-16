<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Application;

use Backendbase\Domain\ExampleCatalog\Contracts\Command\AddEntry;
use Backendbase\Domain\ExampleCatalog\Contracts\Command\ChangeEntry;
use Backendbase\Domain\ExampleCatalog\Contracts\Command\RemoveEntry;
use Backendbase\Domain\ExampleCatalog\Contracts\EntryWriteRepository;
use Backendbase\Domain\ExampleCatalog\Contracts\IntegrationEvents\EntryAdded;
use Backendbase\Domain\ExampleCatalog\Contracts\IntegrationEvents\EntryChanged;
use Backendbase\Domain\ExampleCatalog\Contracts\IntegrationEvents\EntryRemoved;
use Backendbase\Domain\ExampleCatalog\Contracts\IntegrationEvents\V1\EntryAddedPayload;
use Backendbase\Domain\ExampleCatalog\Contracts\IntegrationEvents\V1\EntryChangedPayload;
use Backendbase\Domain\ExampleCatalog\Domain\Entry;
use Backendbase\Shared\Time\Clock;

final readonly class EntryWrites
{
    public function __construct(private EntryWriteRepository $repository, private Clock $clock)
    {
    }

    public function add(AddEntry $command): EntryAdded
    {
        $clock        = $this->clock;
        $now          = $clock->now();
        $entryId      = $command->entryId();
        $type         = $command->type();
        $typeTargetId = $command->typeTargetId();
        $group        = $command->group();
        $isActive     = $command->isActive();
        $key          = $command->key();
        $value        = $command->value();
        $details      = $command->details();
        $entry        = Entry::create($entryId, $type, $typeTargetId, $group, $isActive, $key, $value, $details, $now);
        $repository   = $this->repository;
        $repository->add($entry);

        return new EntryAdded(new EntryAddedPayload($entryId, $type->value, $typeTargetId, $group, $isActive, $key, $value, $details));
    }

    public function change(ChangeEntry $command): EntryChanged
    {
        $repository = $this->repository;
        $identity   = $command->identity();
        $entry      = $repository->getActiveByIdentity($identity);
        $isActive   = $command->isActive();
        $value      = $command->value();
        $details    = $command->details();
        $clock      = $this->clock;
        $now        = $clock->now();
        $entry->change($isActive, $value, $details, $now);
        $repository->save($entry);

        return new EntryChanged(new EntryChangedPayload($entry->id(), $isActive, $value, $details));
    }

    public function remove(RemoveEntry $command): EntryRemoved
    {
        $repository = $this->repository;
        $identity   = $command->identity();
        $entry      = $repository->getActiveByIdentity($identity);
        $clock      = $this->clock;
        $now        = $clock->now();
        $entry->remove($now);
        $repository->save($entry);

        return new EntryRemoved($entry->id());
    }
}
