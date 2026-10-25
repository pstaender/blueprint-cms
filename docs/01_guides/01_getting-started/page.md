---
title: Getting started
---
## Requirements

PHP >= 8.3 and [Composer](https://getcomposer.org).

```sh
composer install
composer serve   # http://localhost:8000
composer test
```

## Folder structure

```
config/             project config (config.php, config.{hostname}.php)
content/            pages, site.json, users.json, redirects.json
public/             document root, index.php is the front controller
site/
  blueprints/       data structures and panel views
    custom-fields/  reusable field definitions
    pages/          page blueprints, one per template
    files/          file blueprints
    users/          user blueprints, one per role
    site.yml        the site blueprint
  config/           site config (overridden by config/)
  templates/        php templates
    partials/       partials, see partial()
```

## Request lifecycle

[`public/index.php`](../../../public/index.php) creates the [`App`](../../../src/App.php), which resolves every `GET`/`HEAD` request:

1. `content/redirects.json`
2. the page for the path, otherwise 404 (`site/templates/404.php`)
3. a 301 redirect to the page's canonical url
4. rendering of the page, see [Templates](../03_templates/page.md)
