<?php

declare(strict_types=1);

namespace Blueprint;

/**
 * A column of a layout row, `blocks()` is a blocks field value (echo renders the blocks).
 */
class LayoutColumn
{
    public function __construct(private readonly array $column, private readonly ?Field $field = null, private readonly ?object $model = null)
    {
    }

    public function id(): string
    {
        return (string) ($this->column["id"] ?? "");
    }

    /**
     * e.g. "1/2"
     */
    public function width(): string
    {
        return (string) ($this->column["width"] ?? "1/1");
    }

    /**
     * The width as column span of a 12 column grid, "1/3" → 4.
     */
    public function span(int $columns = 12): int
    {
        [$a, $b] = array_map("intval", explode("/", $this->width()) + [1 => 1]);

        return $b > 0 ? max(1, min($columns, (int) round($columns * $a / $b))) : $columns;
    }

    public function blocks(): FieldValue
    {
        $field = new Field([...($this->field?->props ?? ["name" => "blocks"]), "type" => "blocks"]);

        return new FieldValue($field->name, $this->column["blocks"] ?? [], $field, $this->model);
    }

    public function toArray(): array
    {
        return $this->column;
    }
}
