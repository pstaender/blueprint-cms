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
