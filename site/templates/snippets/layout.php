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
// `hidden: true` pages (e.g. the rating endpoint) aren't in the menu
$sorted = fn(Collection $pages) => $pages
    ->reject(fn($item) => $item->isHome() || $item->data()->hidden === true)
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
// the version of this repository (written by `composer release`)
$composer = json_decode((string) @file_get_contents(Blueprint\App::instance()->projectDir() . "/composer.json"), true);
$version = $composer["version"] ?? null;
$title ??= $page?->title()->value();

// the panel's line icons (panel/src/icons.json), e.g. for the rating buttons
$panelIcons = json_decode((string) @file_get_contents(Blueprint\App::instance()->projectDir() . "/panel/src/icons.json"), true) ?: [];
$icon = fn(string $name) => '<svg class="docs-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="' . esc($panelIcons[$name] ?? "") . '"/></svg>';

// only for logged in users, opens the current page in the panel
$panelUrl = null;
if (Blueprint\App::instance()->isPanelEnabled() && user() !== null) {
    $panelUrl = $page === null ? "/panel/site" : "/panel/pages/" . implode("/", array_map(rawurlencode(...), explode("/", $page->id())));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title) ?> | <?= esc($siteName) ?></title>
    <link rel="icon" href="<?= esc(static_file_url("assets/images/blueprint_cms.svg")) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@speed-highlight/core@2.1.0/dist/themes/dark.css">
    <link rel="stylesheet" href="<?= esc(static_file_url("assets/css/docs.css")) ?>">
    <script src="https://cdn.jsdelivr.net/npm/fuse.js@7.0.0/dist/fuse.min.js" defer></script>
    <script src="<?= esc(static_file_url("assets/js/docs.js")) ?>" defer></script>
    <script src="<?= esc(static_file_url("assets/js/highlight.js")) ?>" type="module"></script>
</head>
<body>
    <header class="docs-header">
        <div class="docs-header-inner">
            <button type="button" class="docs-menu-toggle" aria-controls="docs-menu" aria-expanded="false">Menu</button>
            <a class="docs-logo" href="/"><img src="<?= esc(static_file_url("assets/images/blueprint_cms.svg")) ?>" alt="" width="28" height="28"><span class="docs-logo-name"><?= esc($siteName) ?></span></a>
            <?php if ($version): ?>
                <span class="docs-version" title="Current version">v<?= esc($version) ?></span>
            <?php endif ?>
            <div class="docs-search" role="search">
                <input type="search" class="docs-search-input" placeholder="Search" aria-label="Search the docs" aria-controls="docs-search-results" aria-expanded="false" autocomplete="off">
                <kbd class="docs-search-key" aria-hidden="true">/</kbd>
                <ul id="docs-search-results" class="docs-search-results" role="listbox" hidden></ul>
            </div>
            <div class="docs-header-links">
                <?php if ($panelUrl): ?>
                    <a class="docs-header-link" href="<?= esc($panelUrl) ?>">Edit in panel</a>
                <?php endif ?>
                <a class="docs-github" href="https://github.com/pstaender/blueprint-cms/" title="Blueprint CMS on GitHub" aria-label="Blueprint CMS on GitHub">
                    <svg viewBox="0 0 16 16" width="22" height="22" aria-hidden="true"><path fill="currentColor" d="M8 0c4.42 0 8 3.58 8 8a8.013 8.013 0 0 1-5.45 7.59c-.4.08-.55-.17-.55-.38 0-.27.01-1.13.01-2.2 0-.75-.25-1.23-.54-1.48 1.78-.2 3.65-.88 3.65-3.95 0-.88-.31-1.59-.82-2.15.08-.2.36-1.02-.08-2.12 0 0-.67-.22-2.2.82-.64-.18-1.32-.27-2-.27-.68 0-1.36.09-2 .27-1.53-1.03-2.2-.82-2.2-.82-.44 1.1-.16 1.92-.08 2.12-.51.56-.82 1.28-.82 2.15 0 3.06 1.86 3.75 3.64 3.95-.23.2-.44.55-.51 1.07-.46.21-1.61.55-2.33-.66-.15-.24-.6-.83-1.23-.82-.67.01-.27.38.01.53.34.19.73.9.82 1.13.16.45.68 1.31 2.69.94 0 .67.01 1.3.01 1.49 0 .21-.15.45-.55.38A7.995 7.995 0 0 1 0 8c0-4.42 3.58-8 8-8Z"/></svg>
                </a>
            </div>
        </div>
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
            <?php // the static build (cli) has no php to store ratings, docs.js shows the form ?>
            <?php if ($page !== null && PHP_SAPI !== "cli"): ?>
                <form class="docs-rating" method="post" action="/rating" hidden>
                    <input type="hidden" name="page" value="<?= esc($page->id()) ?>">
                    <span>Was this page helpful?</span>
                    <button type="submit" name="ratingValue" value="1" title="Yes" aria-label="Yes"><?= $icon("thumbs-up") ?></button>
                    <button type="submit" name="ratingValue" value="-1" title="No" aria-label="No"><?= $icon("thumbs-down") ?></button>
                    <span class="docs-rating-message" role="status"></span>
                </form>
            <?php endif ?>
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
