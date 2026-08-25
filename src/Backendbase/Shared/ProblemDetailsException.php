<?php

declare(strict_types=1);

namespace Backendbase\Shared;

use JsonSerializable;
use Throwable;

interface ProblemDetailsException extends JsonSerializable, Throwable
{
    /** @param array<string, mixed>|null $additional */
    public static function create(string $details, array|null $additional = []): static;

    public function getStatus(): int;

    public function getErrorCode(): string;

    public function getType(): string;

    public function getTitle(): string;

    public function getDetail(): string;

    /** @return array<string, mixed> */
    public function getAdditionalData(): iterable;

    /** @return array<string, mixed> */
    public function toArray(): iterable;
}
