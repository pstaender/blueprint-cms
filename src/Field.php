<?php

declare(strict_types=1);

namespace Blueprint;

use Illuminate\Support\Arr;

/**
 * A normalized field of a blueprint, e.g. `title: {type: text, required: true}`.
 * Field props are passed through to the panel, only some of them are used for validation.
 */
class Field
{
    public const TYPES = [
        "blocks", "buttons", "checkboxes", "color", "date", "datetime", "email", "entries", "filelist", "filepicker",
        "gap", "headline", "hidden", "icon", "info", "layout", "line", "link", "list", "multiselect", "number",
        "object", "pagelist", "pagepicker", "password", "radio", "range", "select", "slug", "stats",
        "structure", "tags", "tel", "text", "textarea", "time", "toggle", "toggles", "url", "userpicker",
        "writer",
    ];

    /**
     * Legacy (Kirby) type names.
     */
    public const ALIASES = [
        "files" => "filepicker",
        "pages" => "pagepicker",
        "users" => "userpicker",
    ];

    /**
     * Fields that only display something in the panel and don't store a value.
     */
    public const DISPLAY_TYPES = ["buttons", "filelist", "gap", "headline", "info", "line", "pagelist", "stats"];

    /**
     * Fields whose value is a list, `min`/`max` limit the number of entries.
     */
    public const DATE_TYPES = ["date", "datetime", "time"];

    /**
     * Max. size of an svg uploaded into an icon field (stored inline as data uri).
     */
    public const MAX_ICON_SVG_SIZE = 50 * 1024;

    public const LIST_TYPES = [
        "blocks", "checkboxes", "entries", "filepicker", "layout", "multiselect", "pagepicker", "structure",
        "tags", "userpicker",
    ];

    public readonly string $name;
    public readonly string $type;

    public function __construct(public readonly array $props)
    {
        $this->name = (string) $props["name"];
        $this->type = (string) $props["type"];
    }

    public static function isType(string $type): bool
    {
        return in_array($type, self::TYPES, true);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->props, $key, $default);
    }

    public function label(): string
    {
        return (string) ($this->props["label"] ?? $this->name);
    }

    public function hasValue(): bool
    {
        return !in_array($this->type, self::DISPLAY_TYPES, true);
    }

    public function isRequired(): bool
    {
        return ($this->props["required"] ?? false) === true;
    }

    /**
     * Nested fields of structure and object fields.
     *
     * @return array<string, Field>
     */
    public function fields(): array
    {
        return array_map(fn(array $props) => new self($props), $this->props["fields"] ?? []);
    }

    /**
     * The fields of each block type of a blocks/layout field.
     *
     * @return array<string, array<string, Field>>
     */
    public function fieldsets(): array
    {
        return array_map(
            fn(array $fieldset) => array_map(fn(array $props) => new self($props), $fieldset["fields"] ?? []),
            is_array($this->props["fieldsets"] ?? null) ? $this->props["fieldsets"] : [],
        );
    }

    public function defaultValue(): mixed
    {
        return $this->props["default"] ?? null;
    }

    /**
     * @return string[] error messages, empty if the value is valid
     */
    public function validate(mixed $value): array
    {
        if (!$this->hasValue()) {
            return [];
        }

        if ($this->isEmpty($value)) {
            return $this->isRequired() ? ["{$this->label()} is required"] : [];
        }

        $errors = [];
        $min = $this->props["min"] ?? null;
        $max = $this->props["max"] ?? null;

        if (in_array($this->type, self::LIST_TYPES, true)) {
            $count = is_array($value) ? count($value) : count(array_filter(array_map("trim", explode(",", (string) $value))));

            if ($min !== null && $count < $min) {
                $errors[] = "{$this->label()} needs at least $min entries";
            }
            if ($max !== null && $count > $max) {
                $errors[] = "{$this->label()} allows at most $max entries";
            }

            return $errors;
        }

        if (in_array($this->type, ["number", "range"], true)) {
            if (!is_numeric($value)) {
                return ["{$this->label()} must be a number"];
            }
            if ($min !== null && $value < $min) {
                $errors[] = "{$this->label()} must be at least $min";
            }
            if ($max !== null && $value > $max) {
                $errors[] = "{$this->label()} must be at most $max";
            }

            return $errors;
        }

        if (!is_scalar($value)) {
            return $this->type === "object" || $this->type === "link" ? [] : ["{$this->label()} must be a single value"];
        }

        $value = (string) $value;
        $length = mb_strlen($value);
        $minLength = $this->props["minlength"] ?? null;
        $maxLength = $this->props["maxlength"] ?? null;
        $pattern = $this->props["pattern"] ?? null;

        if ($minLength !== null && $length < $minLength) {
            $errors[] = "{$this->label()} must be at least $minLength characters long";
        }
        if ($maxLength !== null && $length > $maxLength) {
            $errors[] = "{$this->label()} must be at most $maxLength characters long";
        }
        if (is_string($pattern) && !preg_match("/^(?:$pattern)$/u", $value)) {
            $errors[] = "{$this->label()} doesn't match the required format";
        }

        $isValid = match ($this->type) {
            "email" => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            "url" => filter_var($value, FILTER_VALIDATE_URL) !== false,
            "date", "datetime" => strtotime($value) !== false,
            "time" => preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $value) === 1,
            "select", "radio", "toggles" => $this->staticOptions() === null || in_array($value, $this->staticOptions(), true),
            "icon" => self::isValidIcon($value),
            default => true,
        };

        if (!$isValid) {
            $errors[] = "{$this->label()} is not a valid {$this->type} value";
        }

        return $errors;
    }

    /**
     * An icon name or emoji/short text (one line), or an svg as `data:image/svg+xml` uri up to MAX_ICON_SVG_SIZE.
     */
    public static function isValidIcon(string $value): bool
    {
        if (!str_starts_with($value, "data:")) {
            return mb_strlen($value) <= 32 && !preg_match('/[\r\n]/', $value);
        }

        if (!preg_match('#^data:image/svg\+xml(;base64)?,(.*)$#s', $value, $matches)) {
            return false;
        }

        $svg = $matches[1] !== "" ? base64_decode($matches[2], true) : rawurldecode($matches[2]);

        return is_string($svg) && strlen($svg) <= self::MAX_ICON_SVG_SIZE && str_contains($svg, "<svg");
    }

    /**
     * The option values of `options: [a, b]` or `options: {a: A, b: B}`,
     * null for dynamic options (e.g. `options: query`).
     */
    public function staticOptions(): ?array
    {
        $options = $this->props["options"] ?? null;

        if (!is_array($options) || isset($options["type"])) {
            return null;
        }

        return array_map(
            fn($key, $option) => (string) (is_array($option) ? ($option["value"] ?? $key) : (array_is_list($options) ? $option : $key)),
            array_keys($options),
            $options,
        );
    }

    public function toArray(): array
    {
        return $this->props;
    }

    private function isEmpty(mixed $value): bool
    {
        return $value === null || $value === "" || $value === [];
    }
}
