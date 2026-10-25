<?php

declare(strict_types=1);

namespace Blueprint;

use Illuminate\Support\Arr;

/**
 * A blueprint defines the data structure (fields) of a model and its panel view (tabs → columns → fields).
 *
 * Blueprints are named by their path in `site/blueprints`, e.g. "pages/article" for
 * `site/blueprints/pages/article.yml` (or `.php` returning an array). Missing blueprints
 * fall back to the core blueprints in `src/blueprints`.
 */
class Blueprint
{
    public const EXTENSIONS = ["yml", "yaml", "php"];

    /**
     * Available `options` per model type, `null` means "use the default behaviour".
     */
    public const OPTIONS = [
        "pages" => [
            "changeSlug" => null, "changeStatus" => null, "changeTemplate" => null, "changeTitle" => null,
            "create" => null, "delete" => null, "duplicate" => null, "move" => null, "preview" => null,
            "read" => null, "sort" => null, "update" => null,
        ],
        "site" => ["changeTitle" => null, "preview" => null, "update" => null],
        "files" => [
            "changeName" => null, "changeTemplate" => null, "create" => null, "delete" => null, "read" => null,
            "replace" => null, "sort" => null, "update" => null,
        ],
        "users" => [
            "changeEmail" => null, "changeName" => null, "changePassword" => null, "changeRole" => null,
            "create" => null, "delete" => null, "update" => null,
        ],
    ];

    /** @var array<string, array> raw props by file path */
    private static array $files = [];

    /** @var array<string, Blueprint> normalized blueprints by file path */
    private static array $loaded = [];

    private readonly array $props;

    /** @var array<string, Field> */
    private readonly array $fields;

    public function __construct(public readonly string $name, array $props)
    {
        $props = (new BlueprintNormalizer(self::find(...)))->normalize($props, $name);
        $type = $this->type();

        if (isset(self::OPTIONS[$type])) {
            $props["options"] = BlueprintNormalizer::normalizeOptions($props["options"] ?? null, self::OPTIONS[$type]);
        }

        if ($type === "pages") {
            $props["status"] = BlueprintNormalizer::normalizeStatus($props["status"] ?? null);
        }

        $this->fields = array_map(fn(array $field) => new Field($field), $props["fields"]);
        unset($props["fields"]);
        $this->props = $props;
    }

    /**
     * Loads the first existing blueprint of the given names, e.g. load("pages/article", "pages/default").
     *
     * @throws \InvalidArgumentException if none of the blueprints exists
     */
    public static function load(string ...$names): self
    {
        foreach ($names as $name) {
            if ($file = self::file($name)) {
                return self::$loaded[$file] ??= new self($name, self::find($name));
            }
        }

        throw new \InvalidArgumentException("Blueprint \"" . implode("\", \"", $names) . "\" not found");
    }

    /**
     * The blueprint of a page is chosen by its type (`pages/{type}`), falls back to the
     * page blueprint with `default: true` and then to `pages/default`.
     */
    public static function forPage(Page $page): self
    {
        return self::forType($page->type());
    }

    /**
     * The blueprint of a page type, see forPage().
     */
    public static function forType(string $type): self
    {
        $name = "pages/$type";

        if (self::file($name, includeCore: false) === null) {
            $name = self::defaultName("pages") ?? "pages/default";
        }

        return self::load($name);
    }

    /**
     * The raw props of a blueprint file, null if it doesn't exist.
     */
    public static function find(string $name): ?array
    {
        $file = self::file($name);

        if ($file === null) {
            return null;
        }

        return self::$files[$file] ??= self::read($file);
    }

    /**
     * The blueprint file in `site/blueprints` or (core) `src/blueprints`.
     */
    public static function file(string $name, bool $includeCore = true): ?string
    {
        if (!preg_match('#^[\w-]+(/[\w-]+)*$#', $name)) {
            return null;
        }

        foreach ($includeCore ? self::roots() : [self::roots()[0]] as $root) {
            foreach (self::EXTENSIONS as $extension) {
                if (is_file("$root/$name.$extension")) {
                    return "$root/$name.$extension";
                }
            }
        }

        return null;
    }

    /**
     * Names of all blueprints in a folder, e.g. names("pages") → ["pages/article", "pages/blog"].
     */
    public static function names(string $folder): array
    {
        $names = [];

        foreach (self::roots() as $root) {
            foreach (glob("$root/$folder/*.{" . implode(",", self::EXTENSIONS) . "}", GLOB_BRACE) ?: [] as $file) {
                $names[] = "$folder/" . pathinfo($file, PATHINFO_FILENAME);
            }
        }

        $names = array_values(array_unique($names));
        sort($names);

        return $names;
    }

    public static function roots(): array
    {
        return [App::instance()->siteDir() . "/blueprints", __DIR__ . "/blueprints"];
    }

    /**
     * The name of the first blueprint in a folder that is marked with `default: true`.
     */
    private static function defaultName(string $folder): ?string
    {
        foreach (self::names($folder) as $name) {
            if ((self::find($name)["default"] ?? false) === true) {
                return $name;
            }
        }

        return null;
    }

    private static function read(string $file): array
    {
        $props = str_ends_with($file, ".php")
            ? (static fn() => require func_get_arg(0))($file)
            : ContentFile::readYaml($file);

        if (!is_array($props) || array_is_list($props) && $props !== []) {
            throw new \UnexpectedValueException("The blueprint $file must define a map of props");
        }

        return $props;
    }

    /**
     * The model type, i.e. the first part of the name: pages, site, files, users, blocks, …
     */
    public function type(): string
    {
        return explode("/", $this->name)[0];
    }

    public function title(): string
    {
        return (string) $this->props["title"];
    }

    public function props(): array
    {
        return $this->props;
    }

    /**
     * Any prop, also nested with dot notation, e.g. get("options.delete").
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->props, $key, $default);
    }

    /**
     * All fields of all tabs.
     *
     * @return array<string, Field>
     */
    public function fields(): array
    {
        return $this->fields;
    }

    public function field(string $name): ?Field
    {
        foreach ($this->fields as $field) {
            if (strcasecmp($field->name, $name) === 0) {
                return $field;
            }
        }

        return null;
    }

    /**
     * @return array<string, array> tabs → columns → fields
     */
    public function tabs(): array
    {
        return $this->props["tabs"];
    }

    /**
     * A tab by name, the first tab if no name is given.
     */
    public function tab(?string $name = null): ?array
    {
        return $name === null ? (Arr::first($this->tabs()) ?? null) : ($this->tabs()[$name] ?? null);
    }

    /**
     * The `default` values of all fields that store a value.
     */
    public function defaults(): array
    {
        $defaults = [];

        foreach ($this->fields as $name => $field) {
            if ($field->hasValue() && $field->defaultValue() !== null) {
                $defaults[$name] = $field->defaultValue();
            }
        }

        return $defaults;
    }

    /**
     * Validates data against the fields (including the fields of structures and objects).
     *
     * @return array<string, string[]> errors keyed by field path, e.g. "links.0.url"; empty if valid
     */
    public function validate(array|Data $data): array
    {
        return self::validateFields($this->fields, $data instanceof Data ? $data->toArray() : $data);
    }

    /**
     * @param array<string, Field> $fields
     */
    private static function validateFields(array $fields, array $data, string $prefix = ""): array
    {
        $errors = [];

        foreach ($fields as $name => $field) {
            $value = $data[$name] ?? null;

            if ($messages = $field->validate($value)) {
                $errors[$prefix . $name] = $messages;
            }

            if (is_array($value) && in_array($field->type, ["blocks", "layout"], true)) {
                $errors += self::validateBlocks($field, $value, $prefix . $name);
                continue;
            }

            if (!is_array($value) || $field->fields() === []) {
                continue;
            }

            $rows = $field->type === "structure" ? $value : [$value];

            foreach ($rows as $index => $row) {
                $rowPrefix = $prefix . $name . "." . ($field->type === "structure" ? "$index." : "");
                $errors += is_array($row) ? self::validateFields($field->fields(), $row, $rowPrefix) : [];
            }
        }

        return $errors;
    }

    /**
     * Blocks: "{field}.{index}.{name}", layouts: "{field}.{row}.{column}.{index}.{name}".
     */
    private static function validateBlocks(Field $field, array $value, string $path): array
    {
        $fieldsets = $field->fieldsets();
        $errors = [];
        $blocks = [];

        if ($field->type === "layout") {
            foreach ($value as $row => $layout) {
                foreach ((array) ($layout["columns"] ?? []) as $column => $props) {
                    foreach ((array) ($props["blocks"] ?? []) as $index => $block) {
                        $blocks["$path.$row.$column.$index."] = $block;
                    }
                }
            }
        } else {
            foreach ($value as $index => $block) {
                $blocks["$path.$index."] = $block;
            }
        }

        foreach ($blocks as $prefix => $block) {
            if (is_array($block) && !($block["isHidden"] ?? false) && isset($fieldsets[$block["type"] ?? ""])) {
                $errors += self::validateFields($fieldsets[$block["type"]], (array) ($block["content"] ?? []), $prefix);
            }
        }

        return $errors;
    }

    /**
     * The normalized blueprint, e.g. as JSON for the panel.
     */
    public function toArray(): array
    {
        return [...$this->props, "fields" => array_map(fn(Field $field) => $field->toArray(), $this->fields)];
    }
}
