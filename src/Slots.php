<?php

declare(strict_types=1);

namespace Blueprint;

/**
 * The slots of a snippet: `$slots->title()` or `$slots->title`, null for missing slots.
 */
class Slots
{
    /**
     * @param array<string, string> $slots
     */
    public function __construct(private readonly array $slots)
    {
    }

    public function __get(string $name): ?string
    {
        return $this->slots[$name] ?? null;
    }

    public function __isset(string $name): bool
    {
        return isset($this->slots[$name]);
    }

    public function __call(string $name, array $arguments): ?string
    {
        return $this->slots[$name] ?? null;
    }

    public function toArray(): array
    {
        return $this->slots;
    }
}
