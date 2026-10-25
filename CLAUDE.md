# Blueprint CMS (BPCMS)

Flat file CMS inspired by Kirby. PHP >= 8.3, namespace `Blueprint\` → `src/`. The original spec is in `first_prompt.md`, design decisions are logged in `building_history.log` (append to it when making architectural changes).

## Commands

- `composer install`
- `composer test` (Pest, tests in `tests/`)
- `composer serve` → dev server on http://localhost:8000 (`public/index.php` is the front controller)

## Architecture

- `public/index.php` → `App::create(Config::load(PROJECT_DIR . "/config"))->run()`
- `Config` – defaults (`debug`, `panel`, `contentFolder`, `siteFolder`) < `site/config/config[.{host}].php` < `config/config[.{host}].php`. Config files return arrays (lambdas allowed).
- `App` – singleton (`App::instance()`), wraps a Dumbo router with a catch-all `GET|HEAD *` route:
  1. `content/redirects.json` (`{"301": {"from": "to"}}`)
  2. `Site::find($path)` resolves the slug path to a `Page`, else 404 (`site/templates/404.php`)
  3. canonical url redirect (301): pages with published children end with `/`, others don't; `/home` → `/`
  4. render: the page's own php file (`page.php` / `{slug}.php`) if present – its output is the response, unless it returns an array, which is merged into the page data and rendered with the template. Otherwise `site/templates/{template}.php` → `default.php` → `src/views/default.php`.
- `Page` – a folder with `page.json`/`page.md`/`page.content.md`/`page.php`, or a `{slug}.md`/`{slug}.php` file. `01_blog` → num 1, slug `blog`; leading `.` = draft (only visible for a logged in user). Folder pages win over file pages with the same slug. Data merge order: `page.json` < `page.md` front matter < `page.content.md` (as `content`).
- `Data` – read-only, raw (unescaped) data: maps become `Data` (object/array access, missing keys → `null`), lists stay arrays.
- `ContentFile` – reads json and markdown with yaml front matter (body → `content`).
- `Site` – `content/site.json` + root pages. `User` – `content/users.json`, current user from the session (`$_SESSION["blueprint.user"]` = uid; panel login is not implemented yet).
- `Redirect` exception – thrown by `redirect()`, turned into a response by `App`.
- Global helpers in `src/helpers.php`: `page()`, `site()`, `user()`, `md()`, `esc()`, `dd()`, `redirect()`, `partial()` (`site/templates/partials/`), `static_file_url()` (mtime from `public/`).
- Templates get `$page` and `$site`; always `esc()` data in output, `md()` for markdown.

## Guidelines

Modern PHP, keep it simple, flat class structure, camelCase, self-explaining names, comments only where code isn't obvious, English. Breaking changes are fine until a stable release.

## Not implemented yet

Panel (Vue + Vite), blueprints, plugins, controllers, models, routes in `site/`, file models, sqlite/postgres.
