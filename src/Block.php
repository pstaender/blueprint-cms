<?php

declare(strict_types=1);

namespace Blueprint;

/**
 * A block of a blocks field: {"id": "…", "type": "heading", "isHidden": false, "content": {"text": "…"}}.
 * Its fields are defined by the fieldset (blocks/{type}.yml), echo renders site/templates/snippets/blocks/{type}.php.
 */
class Block extends Values
{
    public function __construct(private readonly array $block, array $fields = [], ?object $model = null)
    {
        parent::__construct(is_array($block["content"] ?? null) ? $block["content"] : [], $fields, $model);
    }

    /**
     * @param array<string, array> $fieldsets normalized fieldsets of the blocks field
     */
    public static function fromArray(array $block, array $fieldsets, ?object $model = null): self
    {
        $fields = $fieldsets[$block["type"] ?? ""]["fields"] ?? [];

        return new self($block, array_map(fn(array $props) => new Field($props), $fields), $model);
    }

    public function id(): string
    {
        return (string) ($this->block["id"] ?? "");
    }

    public function type(): string
    {
        return (string) ($this->block["type"] ?? "");
    }

    public function isHidden(): bool
    {
        return ($this->block["isHidden"] ?? false) === true;
    }

    public function toArray(): array
    {
        return $this->block;
    }

    public function render(): string
    {
        return App::instance()->renderSnippet("blocks/" . $this->type(), ["block" => $this]);
    }

    public function __toString(): string
    {
        return $this->render();
    }
}
