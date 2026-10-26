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

Echoing a `blocks` field renders every block with `site/snippets/blocks/{type}.php` (core: [`src/views/blocks/`](../../../src/views/blocks)). The snippet gets `$block`:

```php
<?php // site/snippets/blocks/heading.php ?>
<h2 class="headline"><?= $block->text() ?></h2>
```

See [Blocks](../../02_reference/01_blueprints/06_blocks/page.md).

## Partials

`partial("head")` renders `site/templates/partials/head.php` with `$page` and `$site`; pass more variables with `partial("teaser", ["item" => $child])`.

## Snippets

Snippets work like in Kirby: `snippet("teaser", ["item" => $child])` renders `site/snippets/teaser.php` with the data, `$page` and `$site`. A missing snippet renders nothing; with a list the first existing one is used: `snippet(["teaser-" . $child->type(), "teaser"])`. `snippet("teaser", $data, true)` returns the html instead of echoing it.

Snippets with slots wrap content:

```php
<?php snippet("card", ["class" => "highlight"], slots: true) ?>
  <?php slot("title") ?><h2>Hello</h2><?php endslot() ?>
  <?php slot() ?><p>The text</p><?php endslot() ?>
<?php endsnippet() ?>
```

```php
<?php // site/snippets/card.php ?>
<div class="<?= esc($class) ?>">
  <?= $slots->title() ?>
  <?= $slot ?>
</div>
```

`$slot` is the default slot (everything between `snippet()` and `endsnippet()` if there are no `slot()` calls), `$slots->name()` the named ones (`null` if missing). Slots aren't escaped.

Partials and snippets are alike: partials throw an exception if they are missing.

See all [helpers](../../02_reference/03_helpers/page.md).
