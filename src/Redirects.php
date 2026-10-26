<?php

declare(strict_types=1);

namespace Blueprint;

/**
 * Redirects from content/redirects.json, grouped by status code:
 * { "301": { "from-foo": "to-bar" } }
 */
class Redirects
{
    /** @param array<string, array{url: string, status: int}> $redirects keyed by normalized path */
    public function __construct(private array $redirects = [])
    {
    }

    public static function fromFile(string $file): self
    {
        $redirects = [];

        foreach (ContentFile::readJson($file) as $status => $mapping) {
            foreach ((array) $mapping as $from => $to) {
                $redirects[self::normalize((string) $from)] = [
                    "url" => self::isAbsolute($to) ? $to : "/" . ltrim($to, "/"),
                    "status" => (int) $status,
                ];
            }
        }

        return new self($redirects);
    }

    /** @return array<string, array{url: string, status: int}> keyed by the path without leading and trailing slashes */
    public function all(): array
    {
        return $this->redirects;
    }

    public function find(string $path): ?Redirect
    {
        $redirect = $this->redirects[self::normalize($path)] ?? null;

        return $redirect ? new Redirect($redirect["url"], $redirect["status"]) : null;
    }

    private static function normalize(string $path): string
    {
        return trim($path, "/");
    }

    private static function isAbsolute(string $url): bool
    {
        return (bool) preg_match('#^[a-z][a-z0-9+.-]*://#i', $url);
    }
}
