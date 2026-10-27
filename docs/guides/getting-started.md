---
title: 'Getting started'
sort: 1
---
## Requirements

* PHP 8.4+
* [Composer](https://getcomposer.org)

## As a composer package

Install Blueprint CMS as a requirement via composer:

```sh
composer require pstaender/blueprintcms

# copies public/index.php, public/.htaccess, public/panel/, creates the users, .gitignore and config
./vendor/bin/blueprint setup

# starts development server
php -S localhost:8000 -t public public/index.php
```

`setup` asks step by step, each step can be skipped:

1. copies `index.php`, `.htaccess` and the panel into `public/` (`public:setup`)
2. creates an admin user if there are no users yet (`admin` is suggested as name, the password needs at least 8 characters), then other users (role `user` or one of the [user blueprints](/reference/blueprints/users))
3. adds the missing lines of Blueprint's recommended `.gitignore` (`setup:gitignore`)
4. adds `public:setup` to the composer scripts (`setup:composer`, see below)
5. writes the most important options to `config/config.php`: downsizing images (`content.optimizeImages`) and php files in the content folder (`content.allowPHP`, editable in the panel by nobody, admins or every user). Options the config already has aren't asked; if `config/config.php` exists, the options are printed to add them by hand

Users can also be created later with `user:create`, see [Command line](/guides/cli).

The website has the folders below except `bin/`, `panel/` and `src/` (they are in `vendor/`). To keep the panel up to date, let composer run `public:setup` after updates in the website's `composer.json` (`setup` and `setup:composer` add it):

```json
"scripts": {
    "post-install-cmd": "blueprint public:setup",
    "post-update-cmd": "blueprint public:setup"
}
```

## Folder structure

```
bin/blueprint       cli, see Command line
config/             project config (config.php, config.{hostname}.php)
content/            pages, site.json, users.json, redirects.json
panel/              source of the panel (Vue + Vite)
public/             document root, index.php is the front controller
  .htaccess         rewrites to index.php for Apache
  panel/            the built panel
site/
  blueprints/       data structures and panel views
    custom-fields/  reusable field definitions
    pages/          page blueprints, one per page type
    blocks/         block types of blocks/layout fields
    files/          file blueprints
    users/          user blueprints, one per role
    site.yml        the site blueprint
  cli/              own bin/blueprint commands
  config/           site config (overridden by config/)
  templates/        php templates, one per page type
    partials/       partials, see partial()
    snippets/       snippets (Kirby style), see snippet()
      blocks/       block snippets, e.g. heading.php
```

## Request lifecycle

`public/index.php` creates the `App`, which resolves every request. `/panel` goes to the [panel](/guides/panel) (if enabled), all other requests to the pages. Pages with their own php file answer every method (so they can handle forms), other pages and files only `GET`, `HEAD` and `OPTIONS` (`405` otherwise):

1. `content/redirects.json`
2. the page for the path, otherwise 404 (`site/templates/404.php`)
3. a 301 redirect to the page's canonical url
4. rendering of the page, see [Templates](/guides/templates)
