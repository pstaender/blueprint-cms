---
title: Plugins
sort: 9
---
A plugin is a folder in `site/plugins/` with an `index.php` or a php file named like the folder (`site/plugins/my-plugin/my-plugin.php`). Folders without one of them are ignored. Plugins are loaded in alphabetical order when the app is created, so `site()`, `page()` and all classes can already be used.

Only the entry file is loaded. Where the rest of the php code lives is up to the plugin, the entry file requires it (`src/` is a common place):

```php
<?php // site/plugins/my-plugin/index.php

require_once __DIR__ . "/src/MyPlugin.php";
```

## Folders

Like the site folder a plugin can have:

```
site/plugins/my-plugin/
  index.php        (or my-plugin.php)
  blueprints/      pages/article.yml, blocks/…, …
  templates/       templates, snippets/, partials/, 404.php
  cli/             commands
  models/          model classes, see [Models](/guides/models)
```

The site's own files win over the files of plugins, plugins win over the core (`src/blueprints`, `src/views`, `src/cli/commands`). Between plugins the first in alphabetical order wins. So `site/templates/article.php` replaces `site/plugins/my-plugin/templates/article.php`, and a plugin can bring the blueprint, template and snippets of a page type.

## Static site generator

`site/plugins/static-site-generator` renders the listed and unlisted pages (no drafts) into a folder of html files:

```sh
bin/blueprint static:build                                      # into static/
bin/blueprint static:build ../build                             # relative to the working directory
bin/blueprint static:build --clear-existing-target-folder       # replaces an existing folder
bin/blueprint static:build --base-path=/my-site                 # hosted in a subfolder
```

The command stops if the folder exists, unless `--clear-existing-target-folder` is given. The project folder, its parents and the content, site and public folders are refused.

- `/` → `index.html`, `/blog/` and `/blog/post` → `blog/index.html` and `blog/post/index.html`
- the used files of the pages (the same as the app serves, see [files](/guides/content#files))
- `404.html` from `site/templates/404.php`
- the redirects of `content/redirects.json` and of pages that redirect as html pages with a `meta refresh`
- `public/` without `index.php`, `panel/` and dot files (after rendering, so the optimized images are included)

Links are site paths (`/blog/`), so the static site is meant for the root of a domain. For a subfolder (like GitHub Pages without an own domain: `https://user.github.io/my-site/`) `--base-path=/my-site` prefixes the root-relative `href`, `src`, `action` and `poster` attributes of the html and the redirects; urls built in JavaScript or CSS aren't changed. Every page is rendered once at build time, so templates and page php files can't depend on query parameters or a logged in user.
