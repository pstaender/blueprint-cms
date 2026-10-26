<?php

declare(strict_types=1);

namespace Blueprint;

/**
 * A plugin is a folder in site/plugins with an `index.php` or `{folder name}.php`, which is loaded when the app is created.
 * Like the site folder it can have `blueprints/`, `templates/` (with `snippets/`, `partials/`) and `cli/`;
 * the site's own files win over the plugins' files, plugins win over the core. Only the entry file is loaded,
 * it requires the plugin's other php files (e.g. from `src/`, but any place works).
 */
class Plugin
{
    public function __construct(public readonly string $name, public readonly string $root)
    {
    }

    /**
     * The plugins in a folder, by name. Folders without an entry file are ignored.
     *
     * @return array<string, Plugin>
     */
    public static function scan(string $dir): array
    {
        $plugins = [];

        foreach (glob("$dir/*", GLOB_ONLYDIR) ?: [] as $root) {
            $plugin = new self(basename($root), $root);

            if ($plugin->entryFile() !== null) {
                $plugins[$plugin->name] = $plugin;
            }
        }

        return $plugins;
    }

    public function entryFile(): ?string
    {
        foreach (["index.php", "$this->name.php"] as $filename) {
            if (is_file("$this->root/$filename")) {
                return "$this->root/$filename";
            }
        }

        return null;
    }

    public function load(): void
    {
        (static fn() => require_once func_get_arg(0))($this->entryFile());
    }
}
