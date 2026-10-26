<?php
/**
 * The docs layout: header, the site tree as menu (left), the content ($slot) and "On this page" (right,
 * filled by docs.js). Pages are sorted by `sort` in their front matter, then by title.
 *
 * @var Blueprint\Page|null $page
 * @var Blueprint\Site $site
 * @var string|null $title
 */

use Illuminate\Support\Collection;

$sortKey = fn($item) => [$item->data()->sort ?? PHP_INT_MAX, mb_strtolower((string) $item->title()->value())];
$sorted = fn(Collection $pages) => $pages
    ->reject(fn($item) => $item->isHome())
    ->sort(fn($a, $b) => $sortKey($a) <=> $sortKey($b))
    ->values();
$isActive = fn($item) => $page !== null && ($page->id() === $item->id() || str_starts_with($page->id(), $item->id() . "/"));

// all pages in menu order, for the previous/next links
$flatten = function (Collection $pages) use (&$flatten, $sorted): array {
    $list = [];
    foreach ($sorted($pages) as $item) {
        $list = [...$list, $item, ...$flatten($item->children())];
    }

    return $list;
};
$all = $flatten($site->children());
$index = $page === null ? false : array_search($page->id(), array_map(fn($item) => $item->id(), $all), true);
$previous = $index !== false && $index > 0 ? $all[$index - 1] : null;
$next = $index !== false && $index < count($all) - 1 ? $all[$index + 1] : null;

$menu = function (Collection $pages, int $depth) use (&$menu, $sorted, $isActive, $page): string {
    $html = "";
    foreach ($sorted($pages) as $item) {
        $current = $page?->id() === $item->id() ? ' aria-current="page"' : "";
        $link = '<a href="' . esc($item->url()) . '"' . $current . ">" . esc($item->title()->value()) . "</a>";

        if ($item->hasChildren()) {
            // sections are always open, deeper levels only on the way to the current page
            $open = $depth === 0 || $isActive($item) ? " open" : "";
            $html .= "<li><details$open><summary>$link</summary><ul>" . $menu($item->children(), $depth + 1) . "</ul></details></li>";
        } else {
            $html .= "<li>$link</li>";
        }
    }

    return $html;
};

$breadcrumb = [];
for ($parent = $page?->parent; $parent !== null; $parent = $parent->parent) {
    array_unshift($breadcrumb, $parent);
}
$siteName = $site->data()->name ?? "Docs";
$title ??= $page?->title()->value();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title) ?> | <?= esc($siteName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@speed-highlight/core@2.1.0/dist/themes/github-light.css" media="(prefers-color-scheme: light)">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@speed-highlight/core@2.1.0/dist/themes/github-dark.css" media="(prefers-color-scheme: dark)">
    <link rel="stylesheet" href="<?= esc(static_file_url("assets/docs.css")) ?>">
    <script src="<?= esc(static_file_url("assets/docs.js")) ?>" defer></script>
    <script src="<?= esc(static_file_url("assets/js/highlight.js")) ?>" type="module"></script>
</head>
<body>
    <header class="docs-header">
        <button type="button" class="docs-menu-toggle" aria-controls="docs-menu" aria-expanded="false">Menu</button>
        <a class="docs-logo" href="/"><?= esc($siteName) ?></a>
        <a class="docs-header-link" href="/panel">Panel</a>
    </header>
    <div class="docs-layout">
        <nav id="docs-menu" class="docs-menu" aria-label="Documentation">
            <ul><?= $menu($site->children(), 0) ?></ul>
        </nav>
        <main class="docs-main">
            <?php if ($breadcrumb): ?>
                <nav class="docs-breadcrumb" aria-label="Breadcrumb">
                    <?php foreach ($breadcrumb as $item): ?>
                        <a href="<?= esc($item->url()) ?>"><?= esc($item->title()->value()) ?></a>
                    <?php endforeach ?>
                </nav>
            <?php endif ?>
            <h1><?= esc($title) ?></h1>
            <article class="docs-text"><?= $slot ?></article>
            <?php if ($previous || $next): ?>
                <nav class="docs-pager" aria-label="Pages">
                    <?php if ($previous): ?>
                        <a class="docs-pager-previous" href="<?= esc($previous->url()) ?>"><small>Previous</small><?= esc($previous->title()->value()) ?></a>
                    <?php endif ?>
                    <?php if ($next): ?>
                        <a class="docs-pager-next" href="<?= esc($next->url()) ?>"><small>Next</small><?= esc($next->title()->value()) ?></a>
                    <?php endif ?>
                </nav>
            <?php endif ?>
        </main>
        <aside class="docs-toc" aria-label="On this page" hidden>
            <h2>On this page</h2>
            <ul></ul>
        </aside>
    </div>
</body>
</html>
