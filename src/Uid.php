<?php

declare(strict_types=1);

namespace Blueprint;

/**
 * Unique ids of pages, files and users (`uid` in their data), e.g. "19qiajkmca".
 * References between models use them, e.g. `page://19qiajkmca`, so they survive renamed slugs.
 */
class Uid
{
    public const LENGTH = 10;
    public const SCHEMES = ["page", "file", "user"];

    public static function generate(): string
    {
        $alphabet = "abcdefghijklmnopqrstuvwxyz0123456789";
        $uid = "";

        for ($i = 0; $i < self::LENGTH; $i++) {
            $uid .= $alphabet[random_int(0, 35)];
        }

        return $uid;
    }

    /**
     * "page://19qiajkmca" → ["page", "19qiajkmca"], null for other values.
     *
     * @return array{0: string, 1: string}|null
     */
    public static function parse(mixed $reference): ?array
    {
        if (!is_string($reference) || !preg_match('#^(page|file|user)://([\w-]+)$#', $reference, $matches)) {
            return null;
        }

        return [$matches[1], $matches[2]];
    }
}
