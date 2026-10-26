<?php

declare(strict_types=1);

namespace Blueprint;

use Illuminate\Support\Collection;

/**
 * A row of a layout field: {"id": "…", "attrs": {}, "columns": [{"id": "…", "width": "1/2", "blocks": [...]}]}.
 * Echo renders site/snippets/blocks/layout.php.
 */
class Layout
{
    public function __construct(private readonly array $layout, private readonly ?Field $field = null, private readonly ?object $model = null)
    {
    }

    public function id(): string
    {
        return (string) ($this->layout["id"] ?? "");
    }

    public function attrs(): Data
    {
        return new Data(is_array($this->layout["attrs"] ?? null) ? $this->layout["attrs"] : []);
    }

    /**
     * @return Collection<int, LayoutColumn>
     */
    public function columns(): Collection
    {
        return (new Collection(is_array($this->layout["columns"] ?? null) ? $this->layout["columns"] : []))
            ->map(fn($column) => new LayoutColumn(is_array($column) ? $column : [], $this->field, $this->model))
            ->values();
    }

    public function toArray(): array
    {
        return $this->layout;
    }

    public function render(): string
    {
        return App::instance()->renderSnippet("blocks/layout", ["layout" => $this]);
    }

    public function __toString(): string
    {
        return $this->render();
    }
}
