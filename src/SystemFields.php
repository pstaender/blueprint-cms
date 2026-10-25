<?php

declare(strict_types=1);

namespace Blueprint;

/**
 * Hidden fields that the cms fills in the data of pages, files and users on every write:
 * `uid`, `createdAt` (first write) and `modifiedAt` (last write), as ISO 8601 datetimes. The site only gets the dates.
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
        $now = date(DATE_ATOM);

        return [
            "createdAt" => self::filled($existing["createdAt"] ?? null) ?? self::filled($data["createdAt"] ?? null) ?? $now,
            "modifiedAt" => $now,
            ...array_diff_key($data, array_flip(self::NAMES)),
        ];
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
