<?php

declare(strict_types=1);

namespace Blueprint;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;

/**
 * The value of a field, returned by field methods of models, e.g. $page->createdAt().
 *
 * Echoing it outputs the value formatted by the field type and html escaped. Other
 * method calls are passed to an illuminate/support object of the value (Carbon for
 * dates, Collection for lists, Stringable otherwise), returned strings are wrapped
 * in a FieldValue again, so they stay escaped:
 *
 *     <?= $page->createdAt()->format("Y.m.d") ?>
 *     <?= $page->title()->upper() ?>
 *     <?php foreach ($page->tags() as $tag): ?>
 *
 * The raw value is $page->createdAt()->value() or $page->data()->createdAt.
 */
class FieldValue implements \IteratorAggregate, \Countable, \JsonSerializable, \Stringable
{
    /**
     * Types whose formatted output is html, everything else is escaped.
     */
    public const HTML_TYPES = ["textarea", "writer", "list", "blocks", "layout"];

    /**
     * @param object|null $model the page, site, user, file (or Values) the value belongs to
     */
    public function __construct(
        public readonly string $name,
        private readonly mixed $value,
        public readonly ?Field $field = null,
        public readonly ?object $model = null,
    ) {
    }

    /**
     * The field type, "text" (or "entries" for lists) if the field isn't defined in the blueprint.
     */
    public function type(): string
    {
        return $this->field?->type ?? (is_array($this->value) ? "entries" : "text");
    }

    public function value(): mixed
    {
        return $this->value;
    }

    public function isEmpty(): bool
    {
        return $this->value === null
            || $this->value === []
            || (is_string($this->value) && trim($this->value) === "");
    }

    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    /**
     * A fallback for empty values, e.g. $page->seoTitle()->or($page->title()).
     */
    public function or(mixed $fallback): self
    {
        if ($this->isNotEmpty()) {
            return $this;
        }

        return $fallback instanceof self ? $fallback : new self($this->name, $fallback, $this->field, $this->model);
    }

    /**
     * The formatted and (except for html types) escaped value.
     */
    public function toString(): string
    {
        if ($this->isEmpty()) {
            return "";
        }

        $type = $this->type();
        $output = match ($type) {
            "textarea", "writer", "list" => File::resolveUrls(self::markdown((string) $this->value), $this->page()),
            "url", "link" => Field::isSafeUrl(self::stringify($this->value)) ? self::stringify($this->value) : "",
            "date", "datetime", "time" => $this->toDate()?->format($this->dateFormat()) ?? "",
            "toggle" => $this->toBool() ? "true" : "false",
            "select", "radio", "toggles", "checkboxes", "multiselect", "tags" => implode(", ", $this->labels()),
            "pagepicker" => $this->toPages()->map(fn(Page $page) => (string) $page->title())->implode(", "),
            "userpicker" => $this->toUsers()->map(fn(User $user) => (string) ($user->data->name ?? $user->email()))->implode(", "),
            "filepicker" => $this->toFiles()->map(fn(File $file) => $file->url())->implode(", "),
            "blocks" => $this->toBlocks()->reject(fn(Block $block) => $block->isHidden())->map(fn(Block $block) => $block->render())->implode(""),
            "layout" => $this->toLayouts()->map(fn(Layout $layout) => $layout->render())->implode(""),
            "password", "structure", "object" => "",
            default => self::stringify($this->value),
        };

        return in_array($type, self::HTML_TYPES, true) ? $output : esc($output);
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function toBool(): bool
    {
        return filter_var($this->value, FILTER_VALIDATE_BOOLEAN);
    }

    public function toInt(): int
    {
        return (int) $this->value;
    }

    public function toFloat(): float
    {
        return (float) $this->value;
    }

    public function toDate(): ?Carbon
    {
        if ($this->isEmpty()) {
            return null;
        }

        if ($this->value instanceof \DateTimeInterface) {
            return Carbon::instance($this->value);
        }

        try {
            return Carbon::parse((string) $this->value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Lists stay lists, comma separated strings (e.g. tags) are split, single values become one entry.
     */
    public function toArray(): array
    {
        return match (true) {
            $this->isEmpty() => [],
            is_array($this->value) => $this->value,
            is_string($this->value) && in_array($this->type(), Field::LIST_TYPES, true)
                => array_values(array_filter(array_map("trim", explode(",", $this->value)), fn($item) => $item !== "")),
            default => [$this->value],
        };
    }

    /**
     * Structure rows become Values, everything else FieldValues (with the same field definition).
     */
    public function toCollection(): Collection
    {
        if ($this->type() === "structure") {
            return (new Collection($this->toArray()))->map(fn($row) => $this->toValues($row));
        }

        $itemField = new Field([...$this->field?->props ?? ["name" => $this->name], "type" => $this->itemType()]);

        return (new Collection($this->toArray()))->map(fn($item) => new self($this->name, $item, $itemField, $this->model));
    }

    /**
     * The type of a single entry of a list, e.g. a tag of a tags field.
     */
    private function itemType(): string
    {
        return match ($this->type()) {
            "checkboxes", "multiselect", "tags" => "select",
            "pagepicker", "userpicker", "filepicker" => $this->type(),
            default => "text",
        };
    }

    /**
     * The nested fields of an object field (or a structure row).
     */
    public function toValues(mixed $value = null): Values
    {
        $value ??= $this->value;

        return new Values(is_array($value) ? $value : [], $this->field?->fields() ?? [], $this->model);
    }

    /**
     * Pages by reference (`page://uid`) or path (`blog/newest_entry`), drafts included.
     */
    public function toPages(): Collection
    {
        return (new Collection($this->toArray()))
            ->map(function ($reference) {
                if (!is_string($reference)) {
                    return null;
                }

                [$scheme, $uid] = Uid::parse($reference) ?? [null, null];

                return $scheme === "page" ? site()->findByUid($uid) : site()->find($reference, includeDrafts: true);
            })
            ->filter()
            ->values();
    }

    /**
     * Files by reference (`file://uid`), filename of the model's page (`cover.jpg`) or path (`blog/cover.jpg`).
     */
    public function toFiles(): Collection
    {
        $page = $this->page();

        return (new Collection($this->toArray()))
            ->map(function ($reference) use ($page) {
                if (!is_string($reference)) {
                    return null;
                }

                [$scheme, $uid] = Uid::parse($reference) ?? [null, null];

                return match (true) {
                    $scheme === "file" => site()->findFileByUid($uid),
                    str_contains($reference, "/") => site()->find(dirname($reference), includeDrafts: true)?->file(basename($reference)),
                    default => $page?->file($reference),
                };
            })
            ->filter()
            ->values();
    }

    public function toFile(): ?File
    {
        return $this->toFiles()->first();
    }

    /**
     * @return Collection<int, Block>
     */
    public function toBlocks(): Collection
    {
        $fieldsets = $this->field?->get("fieldsets") ?? [];

        return (new Collection($this->toArray()))
            ->filter(fn($block) => is_array($block) && isset($block["type"]))
            ->map(fn(array $block) => Block::fromArray($block, is_array($fieldsets) ? $fieldsets : [], $this->model))
            ->values();
    }

    /**
     * @return Collection<int, Layout>
     */
    public function toLayouts(): Collection
    {
        return (new Collection($this->toArray()))
            ->filter(fn($layout) => is_array($layout))
            ->map(fn(array $layout) => new Layout($layout, $this->field, $this->model))
            ->values();
    }

    public function toPage(): ?Page
    {
        return $this->toPages()->first();
    }

    public function toUsers(): Collection
    {
        return (new Collection($this->toArray()))
            ->map(fn($id) => is_string($id) ? User::find(Uid::parse($id)[1] ?? $id) : null)
            ->filter()
            ->values();
    }

    public function toUser(): ?User
    {
        return $this->toUsers()->first();
    }

    /**
     * The option labels of the selected values, e.g. "Sales" for `options: {sales: Sales}`.
     *
     * @return string[]
     */
    public function labels(): array
    {
        $options = $this->field?->get("options");
        $labels = [];

        foreach ($this->toArray() as $value) {
            $labels[] = is_array($options) && !isset($options["type"])
                ? self::optionLabel($options, (string) $value)
                : self::stringify($value);
        }

        return $labels;
    }

    public function getIterator(): \Traversable
    {
        return $this->toCollection()->getIterator();
    }

    public function count(): int
    {
        return count($this->toArray());
    }

    public function jsonSerialize(): mixed
    {
        return $this->value;
    }

    /**
     * Nested fields of objects, otherwise passed to Carbon (dates), Collection (lists) or Stringable.
     * Empty values return an empty FieldValue, so chains like ->format() don't fail.
     */
    public function __call(string $method, array $arguments): mixed
    {
        if ($this->type() === "object") {
            return $this->toValues()->field($method);
        }

        if ($this->isEmpty()) {
            return new self($this->name, null, $this->field, $this->model);
        }

        $target = match (true) {
            in_array($this->type(), Field::DATE_TYPES, true) => $this->toDate(),
            is_array($this->value) || in_array($this->type(), Field::LIST_TYPES, true) => $this->toCollection(),
            default => Str::of(self::stringify($this->value)),
        };

        if ($target === null) {
            throw new \BadMethodCallException("Can't call $method() on the invalid {$this->type()} value of \"$this->name\"");
        }

        $result = $target->$method(...$arguments);

        return is_string($result) || $result instanceof Stringable
            ? new self($this->name, (string) $result, new Field(["name" => $this->name, "type" => "text"]), $this->model)
            : $result;
    }

    /**
     * The page of the model, e.g. for the files of a filepicker or links in markdown.
     */
    private function page(): ?Page
    {
        $model = $this->model instanceof Values ? $this->model->model() : $this->model;

        return match (true) {
            $model instanceof Page => $model,
            $model instanceof File => $model->page,
            default => null,
        };
    }

    private function dateFormat(): string
    {
        $format = $this->field?->get("format");

        return match (true) {
            is_string($format) => $format,
            $this->type() === "time" => "H:i",
            $this->type() === "datetime", $this->field?->get("time") !== null && $this->field?->get("time") !== false => "Y-m-d H:i",
            default => "Y-m-d",
        };
    }

    private static function optionLabel(array $options, string $value): string
    {
        foreach ($options as $key => $option) {
            $optionValue = is_array($option) ? ($option["value"] ?? $key) : (array_is_list($options) ? $option : $key);

            if ((string) $optionValue === $value) {
                return is_array($option) ? (string) ($option["text"] ?? $optionValue) : (string) $option;
            }
        }

        return $value;
    }

    private static function stringify(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? "true" : "false",
            is_scalar($value), $value instanceof \Stringable => (string) $value,
            is_array($value) && array_is_list($value) => implode(", ", array_map(self::stringify(...), array_filter($value, "is_scalar"))),
            default => "",
        };
    }

    /**
     * Markdown with escaped inline html, use md() for markdown with html.
     */
    private static function markdown(string $markdown): string
    {
        return App::instance()->markdown($markdown, markup: false);
    }
}
