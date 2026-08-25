<?php

declare(strict_types=1);

namespace Backendbase\Shared\Primitives;

use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;
use Override;

/** @extends ArrayCollection<array-key, mixed> */
class JsonSerializableArrayCollection extends ArrayCollection implements JsonSerializable
{
    #[Override]
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
