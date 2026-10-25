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

    private readonly Data $data;

    /**
     * @param array<string, Field> $fields
     */
    public function __construct(array $values, private readonly array $fields = [])
    {
        $this->data = new Data($values);
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
