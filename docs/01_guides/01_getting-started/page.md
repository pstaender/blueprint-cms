---
title: Getting started
---
## Requirements

PHP >= 8.3 and [Composer](https://getcomposer.org).

```sh
composer install
composer serve   # http://localhost:8000
composer test
composer panel:build   # the admin panel, see Panel guide
```

## Folder structure

```
bin/blueprint       cli: create users, set passwords
config/             project config (config.php, config.{hostname}.php)
content/            pages, site.json, users.json, redirects.json
panel/              source of the panel (Vue + Vite)
public/             document root, index.php is the front controller
  panel/            the built panel
site/
  blueprints/       data structures and panel views
    custom-fields/  reusable field definitions
    pages/          page blueprints, one per page type
    blocks/         block types of blocks/layout fields
    files/          file blueprints
    users/          user blueprints, one per role
    site.yml        the site blueprint
  config/           site config (overridden by config/)
  templates/        php templates, one per page type
    partials/       partials, see partial()
    blocks/         block snippets, e.g. heading.php
```

## Request lifecycle

[`public/index.php`](../../../public/index.php) creates the [`App`](../../../src/App.php), which resolves every request. `/panel` goes to the [panel](../06_panel/page.md) (if enabled), all other `GET`/`HEAD` requests to the pages:

1. `content/redirects.json`
2. the page for the path, otherwise 404 (`site/templates/404.php`)
3. a 301 redirect to the page's canonical url
4. rendering of the page, see [Templates](../03_templates/page.md)
