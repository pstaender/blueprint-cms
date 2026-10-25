---
title: Templates
---
Templates are plain php files in `site/templates/`, one per page type. A page with `type: article` uses `article.php`:

```php
<?php // site/templates/article.php ?>
<?php partial("head") ?>
<h1><?= $page->title() ?></h1>
<time><?= $page->date()->format("d.m.Y") ?></time>
<?= $page->content() ?>
<?php partial("footer") ?>
```

Lookup: `site/templates/{type}.php` → `page.php` → `default.php` → [`src/views/default.php`](../../../src/views/default.php).

Templates get `$page` and `$site`. Field methods like `$page->date()` return values that are formatted and escaped by their [field type](../../02_reference/02_fields/02_field-methods/page.md). `$page->data()` is raw: use `esc()` for output and `md()` for markdown.

## Page php files

A `page.php` (or `{slug}.php`) renders the page itself:

```php
<?php // content/hello/page.php ?>
Hello! You called <?= esc($page->url()) ?>
```

If it returns an array, the array is merged into the page data and the page is rendered with its template:

```php
<?php
return ["title" => "Computed title", "template" => "article"];
```

## Blocks

Echoing a `blocks` field renders every block with `site/templates/blocks/{type}.php` (core: [`src/views/blocks/`](../../../src/views/blocks)). The snippet gets `$block`:

```php
<?php // site/templates/blocks/heading.php ?>
<h2 class="headline"><?= $block->text() ?></h2>
```

See [Blocks](../../02_reference/01_blueprints/06_blocks/page.md).

## Partials

`partial("head")` renders `site/templates/partials/head.php` with `$page` and `$site`; pass more variables with `partial("teaser", ["item" => $child])`.

See all [helpers](../../02_reference/03_helpers/page.md).
