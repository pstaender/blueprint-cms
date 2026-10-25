---
title: Templates
---
Templates are plain php files in `site/templates/`. A page uses the template named in its `template` data (`default` otherwise):

```php
<?php // site/templates/article.php ?>
<?php partial("head") ?>
<h1><?= esc($page->title()) ?></h1>
<?= md($page->data()->content) ?>
<?php partial("footer") ?>
```

Lookup: `site/templates/{template}.php` → `site/templates/default.php` → [`src/views/default.php`](../../../src/views/default.php).

Templates get `$page` and `$site`. Data is raw: always use `esc()` for output and `md()` for markdown.

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

## Partials

`partial("head")` renders `site/templates/partials/head.php` with `$page` and `$site`; pass more variables with `partial("teaser", ["item" => $child])`.

See all [helpers](../../02_reference/03_helpers/page.md).
