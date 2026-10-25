<?php

declare(strict_types=1);

namespace Blueprint;

use Closure;
use Illuminate\Support\Str;

/**
 * Converts the raw props of a blueprint into a uniform layout: tabs → columns → fields.
 *
 * - `fields` at root level are definitions when there is a layout (`tabs`, `columns`, `sections`),
 *   tabs and columns reference them by name, e.g. `fields: [title, text]`
 * - loose fields are wrapped in a column, loose columns in a `main` tab
 * - (legacy) sections are converted to fields, e.g. `type: pages` → `pagelist`
 * - field groups are replaced by their fields
 * - `extends` (or a string as props) merges the props of another blueprint file into the props
 *
 * Invalid fields don't break the blueprint, they are replaced with an `info` field showing the error.
 */
class BlueprintNormalizer
{
    public const SECTION_FIELDS = [
        "files" => "filelist",
        "info" => "info",
        "pages" => "pagelist",
        "stats" => "stats",
    ];

    /** @var array<string, array> field definitions at root level, keyed by lowercase name */
    private array $definitions = [];

    /** @var array<string, array> all fields placed in the layout, keyed by lowercase name */
    private array $fields = [];

    /**
     * @param Closure(string): ?array $find returns the raw props of a blueprint file, e.g. "custom-fields/seo"
     */
    public function __construct(private readonly Closure $find)
    {
    }

    /**
     * @return array the props with normalized `tabs` and a flat list of all `fields`
     */
    public function normalize(array $props, string $name): array
    {
        $this->definitions = [];
        $this->fields = [];

        $props = $this->extend($props);
        $props["name"] = $name;
        $props["title"] ??= self::label(basename($name));

        if (isset($props["fields"]) && (isset($props["tabs"]) || isset($props["columns"]) || isset($props["sections"]))) {
            // groups stay intact here, so they can be referenced by their name
            foreach ($this->expand($props["fields"], spliceGroups: false) as $field) {
                $this->definitions[strtolower($field["name"])] = $field;
            }
            unset($props["fields"]);
        }

        $props = $this->wrapColumnsInTab($this->wrapFieldsInColumn($this->convertSections($props)));
        $props["tabs"] = $this->normalizeTabs($props["tabs"] ?? []);
        $props["fields"] = array_column($this->fields, null, "name");

        return $props;
    }

    /**
     * Merges the props of the blueprint files given in `extends` (string or list) into the props.
     */
    public function extend(array|string $props): array
    {
        if (is_string($props)) {
            $props = ["extends" => $props];
        }

        $extends = $props["extends"] ?? null;
        unset($props["extends"]);

        foreach ((array) $extends as $name) {
            $mixin = ($this->find)((string) $name)
                ?? throw new \InvalidArgumentException("Blueprint \"$name\" to extend from not found");

            $props = self::merge($this->extend($mixin), $props);
        }

        return $props;
    }

    /**
     * Merges maps recursively, lists (and values) are replaced.
     */
    public static function merge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            $baseValue = $base[$key] ?? null;
            $base[$key] = is_array($value) && is_array($baseValue) && !array_is_list($value) && !array_is_list($baseValue)
                ? self::merge($baseValue, $value)
                : $value;
        }

        return $base;
    }

    /**
     * "seoTitle" → "Seo title", "cover_image" → "Cover image"
     */
    public static function label(string $name): string
    {
        return ucfirst(strtolower(Str::headline($name)));
    }

    public static function errorField(string $name, string $message): array
    {
        return [
            "name" => $name,
            "type" => "info",
            "label" => "Error",
            "theme" => "negative",
            "text" => $message,
            "width" => "1/1",
        ];
    }

    /**
     * `true` keeps the defaults, `false` disables everything, otherwise the given options replace the defaults.
     * Option values can be booleans or maps of user roles, e.g. `delete: {admin: true, "*": false}`.
     */
    public static function normalizeOptions(mixed $options, array $defaults): array
    {
        return match (true) {
            $options === null, $options === true => $defaults,
            $options === false => array_fill_keys(array_keys($defaults), false),
            is_array($options) => [...$defaults, ...$options],
            default => $defaults,
        };
    }

    /**
     * Normalizes the page status definition (`draft`, `unlisted`, `listed`), `draft` always exists.
     */
    public static function normalizeStatus(mixed $status): array
    {
        $defaults = [
            "draft" => ["label" => "Draft", "text" => "The page is only visible for logged in users"],
            "unlisted" => ["label" => "Unlisted", "text" => "The page is public but not listed (no number prefix)"],
            "listed" => ["label" => "Listed", "text" => "The page is public and listed (number prefix)"],
        ];

        if (!is_array($status) || $status === []) {
            return $defaults;
        }

        $normalized = [];

        foreach ($defaults as $key => $default) {
            $options = $status[$key] ?? ($key === "draft" ? true : false);

            if ($options === false) {
                continue;
            }

            $normalized[$key] = match (true) {
                is_string($options) => ["label" => $options, "text" => null],
                is_array($options) => [...$default, ...$options],
                default => $default,
            };
        }

        return $normalized;
    }

    private function normalizeTabs(mixed $tabs): array
    {
        $normalized = [];

        foreach (is_array($tabs) ? $tabs : [] as $name => $props) {
            if ($props === false) {
                continue;
            }

            $props = $this->wrapFieldsInColumn($this->convertSections($this->extend(is_array($props) || is_string($props) ? $props : [])));

            $normalized[$name] = [
                ...$props,
                "name" => (string) $name,
                "label" => $props["label"] ?? self::label((string) $name),
                "icon" => $props["icon"] ?? null,
                "columns" => $this->normalizeColumns($props["columns"] ?? []),
            ];
        }

        return $normalized;
    }

    private function normalizeColumns(mixed $columns): array
    {
        $normalized = [];

        foreach (is_array($columns) ? $columns : [] as $props) {
            if (!is_array($props)) {
                continue;
            }

            $props = $this->convertSections($props);

            $normalized[] = [
                ...$props,
                "width" => $props["width"] ?? "1/1",
                "fields" => $this->place($this->expand($props["fields"] ?? [])),
            ];
        }

        return $normalized;
    }

    /**
     * Adds the fields to the blueprint, all fields of a blueprint share one namespace.
     *
     * @param array<int, array> $fields
     * @return array<string, array>
     */
    private function place(array $fields): array
    {
        $placed = [];

        foreach ($fields as $field) {
            $name = $field["name"];

            if (isset($this->fields[strtolower($name)])) {
                $field = self::errorField("$name-duplicate", "The field \"$name\" already exists in this blueprint");
                $name = $field["name"];
            }

            $this->fields[strtolower($name)] = $field;
            $placed[$name] = $field;
        }

        return $placed;
    }

    /**
     * Resolves and normalizes fields, groups are replaced by their fields.
     *
     * @return array<int, array>
     */
    private function expand(mixed $fields, bool $spliceGroups = true): array
    {
        $expanded = [];

        foreach ($this->resolve($fields) as $props) {
            try {
                $props = $this->normalizeField($props);
            } catch (\Throwable $error) {
                $props = self::errorField($props["name"], $error->getMessage());
            }

            if ($props["type"] === "group" && $spliceGroups) {
                array_push($expanded, ...array_values($props["fields"]));
                continue;
            }

            $expanded[] = $props;
        }

        return $expanded;
    }

    /**
     * Resolves all field shortcuts into props with a name:
     * `- title` (reference), `text: true` (name = type), `title: text` (type), `seo: custom-fields/seo` (extends).
     *
     * @return array<int, array>
     */
    private function resolve(mixed $fields): array
    {
        $resolved = [];

        foreach (is_array($fields) ? $fields : [] as $key => $props) {
            // an inline definition within a list of references, e.g. `- text: {type: textarea}`
            if (is_int($key) && is_array($props) && count($props) === 1 && is_string(array_key_first($props)) && is_array(reset($props))) {
                $key = array_key_first($props);
                $props = reset($props);
            }

            if (is_int($key) && is_string($props)) {
                $resolved[] = $this->definitions[strtolower($props)]
                    ?? self::errorField($props, "The field \"$props\" is not defined in the blueprint's fields");
                continue;
            }

            if ($props === true || $props === null) {
                $props = $this->definitions[strtolower((string) $key)] ?? [];
            } elseif (is_string($props)) {
                // `title: text` sets the type, `seo: custom-fields/seo` extends a blueprint file
                $props = Field::isType(Field::ALIASES[$props] ?? $props) ? ["type" => $props] : ["extends" => $props];
            } elseif (!is_array($props)) {
                continue;
            }

            $props["name"] = is_string($key) ? $key : (string) ($props["name"] ?? $key);
            $resolved[] = $props;
        }

        return $resolved;
    }

    private function normalizeField(array $props): array
    {
        $props = $this->extend($props);
        $name = (string) $props["name"];
        $type = $props["type"] ?? $name;
        $type = Field::ALIASES[$type] ?? $type;

        if ($type !== "group" && !Field::isType($type)) {
            throw new \InvalidArgumentException("Invalid field type \"$type\" for field \"$name\"");
        }

        if (isset($props["fields"])) {
            // nested fields (e.g. of a structure) have their own namespace
            $props["fields"] = array_column($this->expand($props["fields"]), null, "name");
        }

        if ($type === "group") {
            $when = $props["when"] ?? null;
            $fields = $props["fields"] ?? [];

            return [
                "name" => $name,
                "type" => $type,
                "fields" => $when === null ? $fields : array_map(fn(array $field) => ["when" => $when, ...$field], $fields),
            ];
        }

        return [
            ...$props,
            "name" => $name,
            "type" => $type,
            "label" => $props["label"] ?? self::label($name),
            "width" => $props["width"] ?? "1/1",
        ];
    }

    /**
     * Sections are a legacy shortcut, they are converted into fields.
     */
    private function convertSections(array $props): array
    {
        if (!isset($props["sections"])) {
            return $props;
        }

        $fields = $props["fields"] ?? [];

        foreach ($props["sections"] as $name => $section) {
            if ($section === false) {
                continue;
            }

            $section = $this->extend(is_array($section) || is_string($section) ? $section : []);
            $type = $section["type"] ?? $name;
            unset($section["type"]);

            if (isset($section["headline"])) {
                $section["label"] ??= $section["headline"];
                unset($section["headline"]);
            }

            if ($type === "fields") {
                // the fields of a fields section become fields of the parent
                $when = $section["when"] ?? null;
                foreach ($this->resolve($section["fields"] ?? []) as $field) {
                    $fields[] = $when === null ? $field : ["when" => $when, ...$field];
                }
                continue;
            }

            $fields[] = isset(self::SECTION_FIELDS[$type])
                ? [...$section, "name" => (string) $name, "type" => self::SECTION_FIELDS[$type]]
                : self::errorField((string) $name, "Invalid section type \"$type\"");
        }

        unset($props["sections"]);
        $props["fields"] = $fields;

        return $props;
    }

    private function wrapFieldsInColumn(array $props): array
    {
        if (!isset($props["fields"])) {
            return $props;
        }

        $props["columns"] = [["width" => "1/1", "fields" => $props["fields"]]];
        unset($props["fields"]);

        return $props;
    }

    private function wrapColumnsInTab(array $props): array
    {
        if (!isset($props["columns"])) {
            return $props;
        }

        $props["tabs"] = ["main" => ["columns" => $props["columns"]]];
        unset($props["columns"]);

        return $props;
    }
}
