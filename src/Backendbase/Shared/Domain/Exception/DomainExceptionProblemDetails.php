<?php

declare(strict_types=1);

namespace Backendbase\Shared\Domain\Exception;

use function array_merge;
use function count;

// phpcs:disable Generic.Commenting.Todo.Found
trait DomainExceptionProblemDetails
{
    /** @var array<string, mixed>|null $additional*/
    private array|null $additional;

    /** @param array<string, mixed> $additional */
    final private function __construct(private string $detail, array $additional)
    {
        $this->additional = $additional;
        $this->code       = static::CODE;

        parent::__construct($detail, static::STATUS);
    }

    /** @param array<string, mixed>|null $additional */
    public static function create(string $details, array|null $additional = []): static
    {
        return new static(
            $details,
            $additional ?? [],
        );
    }

    public function getStatus(): int
    {
        return static::STATUS;
    }

    public function getType(): string
    {
        return static::TYPE;
    }

    public function getErrorCode(): string
    {
        return static::CODE;
    }

    public function getTitle(): string
    {
        return static::TITLE;
    }

    public function getDetail(): string
    {
        return $this->detail;
    }

    /** @return array<string, mixed> */
    public function getAdditionalData(): iterable
    {
        return $this->additional ?? [];
    }

    /** @return array<string, mixed> */
    public function toArray(): iterable
    {
        $problem        = [
            'status' => static::STATUS,
            'detail' => $this->detail,
            'title'  => static::TITLE,
            'type'   => static::TYPE,
        ];
        $additionalData = $this->getAdditionalData();
        if (count($additionalData) > 0) {
            $problem = array_merge($problem, $additionalData);
        }

        return $problem;
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): iterable
    {
        return $this->toArray();
    }
}
