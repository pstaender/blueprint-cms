<?php

declare(strict_types=1);

namespace Blueprint;

/**
 * Nested values with field methods, e.g. a structure row or an object field:
 *
 *     <?php foreach ($page->links() as $link): ?>
 *       <a href="<?= $link->url() ?>"><?= $link->label() ?></a>
 */
class Values
{
    use HasFields;

    protected readonly Data $data;

    /**
     * @param array<string, Field> $fields
     */
    public function __construct(array $values, private readonly array $fields = [], private readonly ?object $model = null)
    {
        $this->data = new Data($values);
    }

    /**
     * The page, site, user or file the values belong to.
     */
    public function model(): ?object
    {
        return $this->model;
    }

    protected function fieldModel(): object
    {
        return $this->model ?? $this;
    }

    public function data(): Data
    {
        return $this->data;
    }

    protected function fieldDefinition(string $name): ?Field
    {
        foreach ($this->fields as $field) {
            if (strcasecmp($field->name, $name) === 0) {
                return $field;
            }
        }

        return null;
    }
}
