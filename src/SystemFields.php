<?php

declare(strict_types=1);

namespace Blueprint;

/**
 * Hidden fields that the cms fills in the data of pages, files and users on every write:
 * `uid`, `createdAt` (first write) and `modifiedAt` (last write), as ISO 8601 datetimes with milliseconds. The site only gets the dates.
 * Pages in markdown files (`about.md`, `page.md`) don't store them: their dates are the file's modification time
 * (PHP can't read creation times), a `uid` or `createdAt` in the front matter is kept.
 * They work like datetime fields (`$page->modifiedAt()->format("d.m.Y")`) and are hidden in the panel, unless a
 * blueprint shows them, e.g. `modifiedAt: {type: datetime, disabled: true}` (disabled fields aren't saved).
 */
class SystemFields
{
    public const NAMES = ["uid", "createdAt", "modifiedAt"];

    /**
     * The data with system fields first. An existing uid and createdAt can't be changed.
     */
    public static function apply(array $data, array $existing = []): array
    {
        return [
            "uid" => self::filled($existing["uid"] ?? null) ?? self::filled($data["uid"] ?? null) ?? Uid::generate(),
            ...self::applyDates($data, $existing),
        ];
    }

    /**
     * Only `createdAt` and `modifiedAt` (the site has no uid).
     */
    public static function applyDates(array $data, array $existing = []): array
    {
        $now = self::now();

        return [
            "createdAt" => self::filled($existing["createdAt"] ?? null) ?? self::filled($data["createdAt"] ?? null) ?? $now,
            "modifiedAt" => $now,
            ...array_diff_key($data, array_flip(self::NAMES)),
        ];
    }

    /**
     * The current time as stored in the data, with milliseconds, so modifiedAt also tells saves within the same
     * second apart (conflict check).
     */
    public static function now(): string
    {
        return (new \DateTimeImmutable())->format("Y-m-d\\TH:i:s.vP");
    }

    /**
     * The data of a markdown file without system fields, except a `uid` and `createdAt` that are already in its front matter.
     */
    public static function forMarkdown(array $data, array $frontMatter = []): array
    {
        return [
            ...array_filter(array_intersect_key($frontMatter, ["uid" => true, "createdAt" => true]), fn($value) => self::filled($value) !== null),
            ...array_diff_key($data, array_flip(self::NAMES)),
        ];
    }

    /**
     * `modifiedAt` (and `createdAt`, unless set) from the modification time of a markdown file.
     */
    public static function fileDates(array $data, string $file): array
    {
        clearstatcache(true, $file);
        $time = @filemtime($file);

        if ($time === false) {
            return $data;
        }

        $date = (new \DateTimeImmutable("@$time"))->setTimezone(new \DateTimeZone(date_default_timezone_get()))->format("Y-m-d\\TH:i:s.vP");

        return [...$data, "createdAt" => self::filled($data["createdAt"] ?? null) ?? $date, "modifiedAt" => $date];
    }

    /**
     * Definitions for field methods, if the blueprint doesn't define the field itself.
     */
    public static function field(string $name): ?Field
    {
        return match ($name) {
            "uid" => new Field(["name" => "uid", "type" => "hidden"]),
            "createdAt", "modifiedAt" => new Field(["name" => $name, "type" => "datetime"]),
            default => null,
        };
    }

    private static function filled(mixed $value): mixed
    {
        return $value === null || $value === "" ? null : $value;
    }
}
