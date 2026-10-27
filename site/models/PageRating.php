<?php

use Blueprint\Model;
use Blueprint\Page;

/**
 * "Was this page helpful?" ratings of the docs (content/pageRatings.json): `page`, `ratingValue` (1 or -1) and the
 * `ip` of the visitor, who can rate each page once.
 */
class PageRating extends Model
{
    /**
     * Saves the rating, null if this ip has already rated the page.
     */
    public static function rate(Page $page, int $value, string $ip): ?self
    {
        if (!in_array($value, [1, -1], true)) {
            throw new \InvalidArgumentException("The rating must be 1 or -1");
        }

        $reference = self::pageReference($page);

        if (self::all()->contains(fn(self $rating) => $rating->data->page === $reference && $rating->data->ip === $ip)) {
            return null;
        }

        return self::create(["page" => $reference, "ratingValue" => $value, "ip" => $ip]);
    }

    /**
     * Markdown pages have no uid, so they are referenced by their path.
     */
    public static function pageReference(Page $page): string
    {
        return $page->uid() !== null ? "page://" . $page->uid() : $page->id();
    }

    public function panelTitle(): string
    {
        return ($this->data->ratingValue === 1 ? "👍 " : "👎 ") . $this->data->page;
    }
}
