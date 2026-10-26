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
    /**
     * Block types of blocks and layout fields without `fieldsets`, see src/blueprints/blocks/.
     */
    public const DEFAULT_FIELDSETS = ["heading", "text", "list", "image", "gallery", "quote", "code", "markdown", "video", "line"];

    public const DEFAULT_LAYOUTS = ["1/1", "1/2, 1/2", "1/3, 2/3", "2/3, 1/3", "1/3, 1/3, 1/3"];

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

    /** @var array<string, true> lowercase names of the definitions used in the layout */
    private array $used = [];

    /**
     * @param Closure(string): ?array $find returns the raw props of a blueprint file, e.g. "custom-fields/seo"
     */
    /**
     * @param string[] $fieldsetParents block types being normalized, to stop recursive fieldsets
     */
    public function __construct(private readonly Closure $find, private readonly array $fieldsetParents = [])
    {
    }

    /**
     * @return array the props with normalized `tabs` and a flat list of all `fields`
     */
    public function normalize(array $props, string $name): array
    {
        $this->definitions = [];
        $this->fields = [];
        $this->used = [];

        $props = $this->extend($props);
        $props["name"] = $name;
        $props["title"] ??= self::label(basename($name));

        if (isset($props["fields"]) && (isset($props["tabs"]) || isset($props["columns"]) || isset($props["sections"]))) {
            // groups stay intact here, so they can be referenced by their name
            foreach ($this->expand($props["fields"], spliceGroups: false) as $field) {
                $this->definitions[strtolower($field["name"])] = $field;
            }
            unset($props["fields"]);
            $this->used = [];
        }

        $props = $this->wrapColumnsInTab($this->wrapFieldsInColumn($this->convertSections($props)));
        $props["tabs"] = $this->placeUnusedFields($this->normalizeTabs($props["tabs"] ?? []));
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

            // draft can't be disabled, every page can be taken offline
            if ($options === false && $key !== "draft") {
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

    /**
     * `fields: true` in a tab or column gets all field definitions that aren't used anywhere else,
     * in the order of the definitions. Only the first of these columns gets them.
     */
    private function placeUnusedFields(array $tabs): array
    {
        $isPlaced = false;

        foreach ($tabs as $tabName => $tab) {
            foreach ($tab["columns"] as $index => $column) {
                if (!($column["unusedFields"] ?? false)) {
                    continue;
                }

                unset($tabs[$tabName]["columns"][$index]["unusedFields"]);

                if ($isPlaced) {
                    continue;
                }

                $unused = array_filter(
                    $this->definitions,
                    fn(array $field, string $name) => !isset($this->used[$name]) && !isset($this->fields[$name]),
                    ARRAY_FILTER_USE_BOTH,
                );
                $tabs[$tabName]["columns"][$index]["fields"] = $this->place($this->expand(array_values($unused)));
                $isPlaced = true;
            }
        }

        return $tabs;
    }

    private function normalizeColumns(mixed $columns): array
    {
        $normalized = [];

        foreach (is_array($columns) ? $columns : [] as $props) {
            if (!is_array($props)) {
                continue;
            }

            $props = $this->convertSections($props);
            $unusedFields = ($props["fields"] ?? null) === true;

            $normalized[] = [
                ...$props,
                "width" => $props["width"] ?? "1/1",
                "fields" => $unusedFields ? [] : $this->place($this->expand($props["fields"] ?? [])),
                ...($unusedFields ? ["unusedFields" => true] : []),
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
                $this->used[strtolower($props)] = true;
                $resolved[] = $this->definitions[strtolower($props)]
                    ?? self::errorField($props, "The field \"$props\" is not defined in the blueprint's fields");
                continue;
            }

            if ($props === true || $props === null) {
                $this->used[strtolower((string) $key)] = true;
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

        if ($type === "blocks" || $type === "layout") {
            $props["fieldsets"] = $this->normalizeFieldsets($props["fieldsets"] ?? null);
        }

        if ($type === "layout") {
            $props["layouts"] = array_map(
                fn($layout) => array_map("trim", is_array($layout) ? $layout : explode(",", (string) $layout)),
                array_values((array) ($props["layouts"] ?? self::DEFAULT_LAYOUTS)),
            );
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
     * Block types of a blocks/layout field: `[heading, text]` (site/blueprints/blocks/{type}.yml or core),
     * a map with inline or extended definitions, or groups (`{type: group, label, fieldsets: [...]}`).
     *
     * @return array<string, array> {type, label, icon, group, fields, tabs} by type
     */
    private function normalizeFieldsets(mixed $fieldsets, ?string $group = null): array
    {
        $normalized = [];

        foreach (is_array($fieldsets) ? $fieldsets : self::DEFAULT_FIELDSETS as $key => $props) {
            $type = is_int($key) ? (string) (is_string($props) ? $props : $props["type"] ?? $key) : $key;

            if ($props === false) {
                continue;
            }

            if (is_array($props) && ($props["type"] ?? null) === "group") {
                $normalized = [...$normalized, ...$this->normalizeFieldsets($props["fieldsets"] ?? [], $props["label"] ?? self::label($type))];
                continue;
            }

            try {
                $props = match (true) {
                    is_array($props) => self::merge(($this->find)("blocks/$type") ?? [], $props),
                    is_string($props) && is_string($key) => $this->extend($props),
                    default => ($this->find)("blocks/$type") ?? throw new \InvalidArgumentException("Block type \"$type\" not found"),
                };

                if (in_array($type, $this->fieldsetParents, true)) {
                    throw new \InvalidArgumentException("The block type \"$type\" can't contain itself");
                }

                $label = $props["title"] ?? $props["label"] ?? $props["name"] ?? self::label($type);
                unset($props["name"]);
                $blueprint = (new self($this->find, [...$this->fieldsetParents, $type]))->normalize($props, "blocks/$type");

                $normalized[$type] = [
                    "type" => $type,
                    "label" => (string) $label,
                    "icon" => $props["icon"] ?? null,
                    "group" => $group,
                    "fields" => $blueprint["fields"],
                    "tabs" => $blueprint["tabs"],
                ];
            } catch (\Throwable $error) {
                $normalized[$type] = [
                    "type" => $type,
                    "label" => self::label($type),
                    "icon" => null,
                    "group" => $group,
                    "fields" => ["error" => self::errorField("error", $error->getMessage())],
                    "tabs" => [],
                ];
            }
        }

        return $normalized;
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
