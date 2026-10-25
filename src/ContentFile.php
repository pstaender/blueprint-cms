<?php

declare(strict_types=1);

namespace Blueprint;

use Symfony\Component\Yaml\Yaml;

/**
 * Reads page data from .json and .md (yaml front matter + markdown body) files.
 */
class ContentFile
{
    public static function read(string $file): array
    {
        return match (pathinfo($file, PATHINFO_EXTENSION)) {
            "json" => self::readJson($file),
            "md" => self::parseMarkdown(file_get_contents($file)),
            "yml", "yaml" => self::readYaml($file),
            default => [],
        };
    }

    public static function readJson(string $file): array
    {
        if (!is_file($file)) {
            return [];
        }

        return (array) json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * Writes data as json, or as markdown with yaml front matter (`content` becomes the body).
     */
    public static function write(string $file, array $data): void
    {
        $output = match (pathinfo($file, PATHINFO_EXTENSION)) {
            "json" => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n",
            "md" => self::toMarkdown($data),
            default => throw new \InvalidArgumentException("Can't write content to $file"),
        };

        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0775, true);
        }

        if (file_put_contents($file, $output, LOCK_EX) === false) {
            throw new \RuntimeException("Can't write $file");
        }
    }

    public static function toMarkdown(array $data): string
    {
        $content = (string) ($data["content"] ?? "");
        unset($data["content"]);

        $frontMatter = $data === [] ? "" : "---\n" . Yaml::dump($data, 4, 2) . "---\n";

        return $frontMatter . ($content === "" ? "" : "$content\n");
    }

    public static function readYaml(string $file): array
    {
        return is_file($file) ? (array) Yaml::parseFile($file) : [];
    }

    /**
     * The front matter becomes the data, the markdown body becomes `content`.
     */
    public static function parseMarkdown(string $markdown): array
    {
        $markdown = str_replace("\r\n", "\n", $markdown);

        if (!preg_match('/\A---\n(.*?)\n?^---$\n?(.*)\z/ms', $markdown, $matches)) {
            return self::withContent([], $markdown);
        }

        return self::withContent((array) Yaml::parse($matches[1]), $matches[2]);
    }

    private static function withContent(array $data, string $body): array
    {
        $body = trim($body);

        if ($body !== "") {
            $data["content"] = $body;
        }

        return $data;
    }
}
