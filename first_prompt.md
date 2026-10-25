# Blueprint CMS (BPCMS)

Blueprint CMS is a flat file cms with optionally database (sqlite or postgres).

It is inspired by the Kirby CMS, sharing the following ideas and foundations:

* blueprints are yml files (or php files return arrays with values and lambdas), which define data structures and the admin panel
* we have site, pages, users, files as models and data objects
* using pure php for templating
* using config.php and config.{hostname}.php for configuration (returning arrays with values and lambdas)
* has a content/ folder, where content lives
* has a site/ folder, where every project related php code lives

## Folder structure

/content
/config
/site
  config/
  bluebrints/
    custom-fields/
    files/
    pages/
    blocks/
  plugins/
  controllers/
  models/
  routes/
  panel/
  users/
  templates/
    snippets/
/public

## Framework / CMS Base components

* https://github.com/notrab/dumbo
* https://github.com/illuminate/support
* https://github.com/michelf/php-markdown

## Panel frontend

* VueJS with ViteJS

## Tests

Use PHP pest

## Coding and architectural guidelines

* write modern PHP
* keep it simple stupid / better simple than smart
* only comment on parts, where the code is not self-explaining
* separation of concernes
* everything is a file / everything is adresses as an url/path
* use self explaining namin
* use camelCaseNames if possible
* write in english
* keep the classes and model structure as flat as possible

## Namespace

Blueprint, e.g.

Blueprint\App
Blueprint\Page
Blueprint\Blueprint

## Global Template Helpers

* page() // page() returns current page; or find a page, e.g. page("blog")
* md() // markdown
* esc() // escape for html output
* dd() (like the laravel dd())
* redirect() // redirect("/test") or redirect("/tmp_redirect", 307)
* site() // return the current site(), data stored in content/site.json
* user()  // user() returns the current user, user("admin") returns the user with the email/username "admin"
* partial() // partial("head") renders php file in site/templates/partials/head.php
* static_file_url() // static_file_url("/assets/css/layout.css") will output the url with the timestamp of the file, s.th. like  /assets/css/layout.css?132154646 

## First Task

Write a cms/framework, that handles the routing for accessing the content, you can find the content in content.
For that the config/ is read and the site/ folder is inspected with the existing (and site/ has also a site/config).
In the config the following basic settings can be set (all settings are optional, the defaults are set here):

```php
[
  "debug" => false, // shows details error traces and warnings
  "panel" => true, // enables the admin panel, will be implemented later
  "contentFolder" =>  'content', // will be assumed to be relative to PROJECT_DIR
  "siteFolder" => 'site', // will be assumed to be relative to PROJECT_DIR
]
```

Make the following content on the content folder accessible:

/ => home.md (could also be `home.php`, `home/page.php`, `home/page.json` and `home/page.md`, by the panel created pages the default will be `/home/page.json` and the folder `/home` containing all uploaded media like images etc
/imprint => imprint/page.md
/blog => 01_blog/page.php (will also redirect to /blog/ because it contains subpages)
/blog/newest_entry => 01_blog/01_newest_entry/page.json

Draft pages start with a `.` and are not accessible by default (only when you are logged in the panel).

Redirects:

Redirects are read from content/redirects.json, `from-foo` redirects to `to-bar` with a 301 redirect.

## Page

page() (shortcut for `Blueprint\Page::currrent()`) returns a Page Object, all raw/unescaped (nested) data from json/md is available via:

`page()->data()->title` and `page()->data()->content`, in the md following rules apply:

```md
---
title: Welcome
template: framework
some:
  nested:
    - value
---
## Hello and welcome
```

becomes `page()->data()->title`, `page()->data()->some->nested[]`; And `page()->data()->content` return `## Hello and welcome`

It can also be combined with a page json holding complex nested data, and the `page.content.md`, see `01_blog/02_second_newest_entry`.

As stated before, by the panel later, the default way be to create for every page a folder containing a `page.json` file. This `md` support is just to make the site also content manageable with a plaintext/markdown editor.

Create also a CLAUDE.md. Log / Describe your main ideas and steps along the building process in building_history.log.
You are allowed to commit. For now, changes can be breaking since no stable version is released yet.
