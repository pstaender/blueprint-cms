# Blueprint CMS

A flat file CMS for PHP, inspired by [Kirby](https://getkirby.com). Content lives in plain files (JSON and Markdown with YAML front matter), no database needed. Blueprints (YAML or PHP) describe the fields of pages, files, users and the site; they drive the templates' field methods, the validation and the admin panel.

> **Alpha:** Blueprint CMS is in an early alpha state. APIs, file formats and the panel can still change without notice, and it isn't ready for production sites yet.

## Features

- pages as folders or single files (`page.json`, `page.md`, `{slug}.md`), drafts, listed and unlisted pages, redirects
- blueprints with tabs, columns and many field types (text, markdown, blocks, layouts, structures, pickers, …)
- plain PHP templates with escaped field methods, snippets and partials
- an admin panel (Vue): page tree, page and file management, drag & drop, a markdown editor, users and roles, passkey login
- uids for pages, files and users, so references survive renamed slugs
- AVIF versions of content images
- a command line tool (`bin/blueprint`) for users and maintenance

## Requirements

PHP >= 8.3, [Composer](https://getcomposer.org) and Node.js (to build the panel).

## Quick start

```sh
composer install
composer panel:build                          # builds the panel into public/panel
bin/blueprint user:create admin@example.com   # asks for a password
composer serve                                # http://localhost:8000, panel at /panel
composer test
```

## Documentation

The documentation is a Blueprint CMS site itself: its content is in [`docs/`](docs) (guides, reference, examples) and this repository's config renders it, so `composer serve` shows it at http://localhost:8000. Start with [Getting started](docs/guides/getting-started.md).

## Releases

`composer release` suggests the next [semantic version](https://semver.org) after the latest git tag (pre-releases like `0.1.0-alpha.1` → `0.1.0-alpha.2`, `0.1.0-beta.1`, `0.1.0`) or takes your own (`composer release 0.2.0`). It writes the version into `composer.json` and `panel/package.json`, commits them and creates the tag `v0.2.0`; push it with `git push && git push origin v0.2.0`.

## License

[MIT](LICENSE)
