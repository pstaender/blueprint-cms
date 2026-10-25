<?php

declare(strict_types=1);

namespace Blueprint;

use ArrayAccess;
use ArrayIterator;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * Raw (unescaped) nested content data with object access:
 * maps become Data objects, lists stay arrays, missing keys return null.
 */
class Data implements ArrayAccess, IteratorAggregate, JsonSerializable
{
    private array $values = [];

    public function __construct(array $values = [])
    {
        foreach ($values as $key => $value) {
            $this->values[$key] = self::wrap($value);
        }
    }

    private static function wrap(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        return array_is_list($value) ? array_map(self::wrap(...), $value) : new self($value);
    }

    private static function unwrap(mixed $value): mixed
    {
        return match (true) {
            $value instanceof self => $value->toArray(),
            is_array($value) => array_map(self::unwrap(...), $value),
            default => $value,
        };
    }

    public function __get(string $key): mixed
    {
        return $this->values[$key] ?? null;
    }

    public function __isset(string $key): bool
    {
        return isset($this->values[$key]);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    public function merge(array $values): self
    {
        return new self(array_replace($this->toArray(), $values));
    }

    public function toArray(): array
    {
        return array_map(self::unwrap(...), $this->values);
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->values[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->values[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \LogicException("Data is read-only");
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException("Data is read-only");
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->values);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
