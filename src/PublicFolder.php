<?php

declare(strict_types=1);

namespace Blueprint;

/**
 * Copies what a website's public folder needs from the package (`bin/blueprint public:build`): the front controller,
 * the .htaccess and the built panel. Existing front controller and .htaccess are kept unless forced, they may be
 * customized; the panel is always replaced.
 */
class PublicFolder
{
    public const FILES = ["index.php", ".htaccess"];

    public function __construct(public readonly string $source = __DIR__ . "/../public")
    {
    }

    /** @return string[] what was done, one line per file/folder */
    public function copyTo(string $target, bool $force = false): array
    {
        if (!is_file("$this->source/panel/index.html")) {
            throw new \RuntimeException("The panel isn't built, run `composer panel:build` in " . dirname(realpath($this->source) ?: $this->source));
        }

        if (is_dir($target) && realpath($target) === realpath($this->source)) {
            return ["$target is the package's own public folder, nothing to copy"];
        }

        if (!is_dir($target) && !mkdir($target, 0755, true)) {
            throw new \RuntimeException("Can't create $target");
        }

        $done = [];
        foreach (self::FILES as $file) {
            if (is_file("$target/$file") && !$force) {
                $done[] = "Kept $target/$file (--force replaces it)";
                continue;
            }
            copy("$this->source/$file", "$target/$file");
            $done[] = "Copied $target/$file";
        }

        self::remove("$target/panel");
        self::copyFolder("$this->source/panel", "$target/panel");
        $done[] = "Copied $target/panel";

        return $done;
    }

    private static function copyFolder(string $source, string $target): void
    {
        mkdir($target, 0755, true);
        foreach (scandir($source) as $name) {
            if ($name === "." || $name === "..") {
                continue;
            }
            is_dir("$source/$name") ? self::copyFolder("$source/$name", "$target/$name") : copy("$source/$name", "$target/$name");
        }
    }

    private static function remove(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            unlink($path);
        } elseif (is_dir($path)) {
            foreach (array_diff(scandir($path), [".", ".."]) as $name) {
                self::remove("$path/$name");
            }
            rmdir($path);
        }
    }
}
