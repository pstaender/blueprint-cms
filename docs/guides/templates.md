---
title: Templates
sort: 3
---
Templates are plain php files in `site/templates/`, one per page type. A page with `type: article` uses `article.php`:

```php
<?php // site/templates/article.php ?>
<?= partial("head") ?>
<h1><?= $page->title() ?></h1>
<time><?= $page->date()->format("d.m.Y") ?></time>
<?= $page->content() ?>
<?= partial("footer") ?>
```

Lookup: `site/templates/{type}.php` → `page.php` → `default.php` → `src/views/default.php`. A page with a `template` (in its data) uses that template instead of its type's, the blueprint stays the one of its type: `template: article-wide` renders `article-wide.php` with the fields of `pages/article.yml`.

Templates get `$page` and `$site`. Field methods like `$page->date()` return values that are formatted and escaped by their [field type](/reference/fields/field-methods). `$page->data()` is raw: use `esc()` for output and `md()` for markdown.

## Page php files

php files in the content folder are ignored unless they are enabled in the config:

```php
<?php // config/config.php
return [
    "content" => [
        "allowPHP" => true,
    ],
];
```

A `page.php` (or `{slug}.php`) then renders the page itself:

```php
<?php // content/hello/page.php ?>
Hello! You called <?= esc($page->url()) ?>
```

If it returns an array, the array is merged into the page data and the page is rendered with its template:

```php
<?php
return ["title" => "Computed title", "template" => "article"];
```

The array only exists while the page is rendered, so menus, lists and the panel don't see it and show the slug as the title. For a fixed title use a folder page with `page.php` and a `page.json` (or `page.md`) for its data:

```
content/releases/page.php
content/releases/page.json   {"title": "Releases"}
```

### Editing in the panel

With `"allowPHP" => ["edit" => ["admin", "jane@example.com"]]` these roles and users can edit the php files in the panel: the page shows a php editor (syntax highlighting, Tab indents) instead of its fields. Entries that aren't a role are users, matched by email or `name`. Before saving, the code is checked for syntax errors (like `php -l`); the error and its line are shown and the file can still be saved with *Save anyway*. Anyone who can edit them can run any code on the server, so only allow trusted users.

Only admins and these roles and users can change pages with a php file in the panel: edit their fields, rename, move, sort, change the status or delete them (also by deleting a parent page). Admins can't edit the code itself unless they are in `edit`. The type of a page with a php file can't be changed, its php file renders it.

## Blocks

Echoing a `blocks` field renders every block with `site/templates/snippets/blocks/{type}.php` (core: `src/views/blocks/`). The snippet gets `$block`:

```php
<?php // site/templates/snippets/blocks/heading.php ?>
<h2 class="headline"><?= $block->text() ?></h2>
```

See [Blocks](/reference/blueprints/blocks).

## Partials

`<?= partial("head") ?>` outputs `site/templates/partials/head.php` with `$page` and `$site`; pass more variables with `partial("teaser", ["item" => $child])`. `partial()` returns the html (it doesn't echo) and throws an exception if the partial doesn't exist. Instead of a name, it takes an absolute path to a php file, so IDEs can follow it: `<?= partial(__DIR__ . "/partials/head.php") ?>`.

## Snippets

Snippets work like in Kirby: `snippet("teaser", ["item" => $child])` renders `site/templates/snippets/teaser.php` with the data, `$page` and `$site`. A missing snippet renders nothing; with a list the first existing one is used: `snippet(["teaser-" . $child->type(), "teaser"])`. `snippet("teaser", $data, true)` returns the html instead of echoing it.

Snippets with slots wrap content:

```php
<?php snippet("card", ["class" => "highlight"], slots: true) ?>
  <?php slot("title") ?><h2>Hello</h2><?php endslot() ?>
  <?php slot() ?><p>The text</p><?php endslot() ?>
<?php endsnippet() ?>
```

```php
<?php // site/templates/snippets/card.php ?>
<div class="<?= esc($class) ?>">
  <?= $slots->title() ?>
  <?= $slot ?>
</div>
```

`$slot` is the default slot (everything between `snippet()` and `endsnippet()` if there are no `slot()` calls), `$slots->name()` the named ones (`null` if missing). Slots aren't escaped.

Partials and snippets are alike: partials throw an exception if they are missing.

See all [helpers](/reference/helpers).
