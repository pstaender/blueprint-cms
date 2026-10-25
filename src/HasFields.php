<?php

declare(strict_types=1);

namespace Blueprint;

/**
 * Field methods for models: $page->createdAt() returns the FieldValue of the `createdAt` field.
 * Use field("name") for fields whose name is also a method of the model, e.g. field("url").
 */
trait HasFields
{
    abstract public function data(): Data;

    abstract protected function fieldDefinition(string $name): ?Field;

    public function field(string $name): FieldValue
    {
        $field = $this->fieldDefinition($name) ?? SystemFields::field($name);
        $values = $this->data()->toArray();
        $value = $values[$name] ?? ($field !== null ? $values[$field->name] ?? null : null);

        return new FieldValue($field?->name ?? $name, $value, $field, $this->fieldModel());
    }

    /**
     * The model that field values belong to, e.g. to find the files of a filepicker.
     */
    protected function fieldModel(): object
    {
        return $this;
    }

    public function __call(string $name, array $arguments): FieldValue
    {
        return $this->field($name);
    }
}
