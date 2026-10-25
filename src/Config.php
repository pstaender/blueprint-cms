<?php

declare(strict_types=1);

namespace Blueprint;

use Illuminate\Support\Arr;

class Config
{
    public const DEFAULTS = [
        "debug" => false,
        "panel" => true,
        "contentFolder" => "content",
        "siteFolder" => "site",
        // converts all local <img> of rendered pages to AVIF, see ImageOptimizer
        "optimizeContentImages" => false,
    ];

    public function __construct(
        private array $values = [],
        public readonly string $projectDir = "",
    ) {
    }

    /**
     * Loads config.php and config.{hostname}.php from the given folder.
     * The project dir defaults to the parent of the config folder.
     */
    public static function load(string $configDir, ?string $hostname = null, ?string $projectDir = null): self
    {
        return new self(
            self::readFolder($configDir, $hostname ?? self::hostname()),
            $projectDir ?? dirname($configDir),
        );
    }

    public static function readFolder(string $dir, string $hostname): array
    {
        $files = ["$dir/config.php"];

        if ($hostname !== "") {
            $files[] = "$dir/config.$hostname.php";
        }

        $values = [];

        foreach ($files as $file) {
            if (is_file($file)) {
                $values = array_replace_recursive($values, (array) require $file);
            }
        }

        return $values;
    }

    public static function hostname(): string
    {
        $host = $_SERVER["HTTP_HOST"] ?? $_SERVER["SERVER_NAME"] ?? (PHP_SAPI === "cli" ? gethostname() : "");
        $host = strtolower(preg_replace('/:\d+$/', "", (string) $host));

        // the hostname ends up in a file path
        return preg_match('/^[a-z0-9.-]+$/', $host) ? $host : "";
    }

    /**
     * Merges in values with lower priority, e.g. the site/config.
     */
    public function withFallback(array $values): self
    {
        return new self(array_replace_recursive(self::DEFAULTS, $values, $this->values), $this->projectDir);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get(array_replace(self::DEFAULTS, $this->values), $key, $default);
    }

    public function all(): array
    {
        return array_replace(self::DEFAULTS, $this->values);
    }

    /**
     * Resolves a configured folder relative to the project dir (absolute paths stay as they are).
     */
    public function path(string $key): string
    {
        $path = (string) $this->get($key);

        return str_starts_with($path, "/") ? rtrim($path, "/") : rtrim("$this->projectDir/$path", "/");
    }
}
