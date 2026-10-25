# Blueprint CMS (BPCMS)

Flat file CMS inspired by Kirby. PHP >= 8.3, namespace `Blueprint\` → `src/`. The original spec is in `first_prompt.md`, design decisions are logged in `building_history.log` (append to it when making architectural changes). User documentation lives in `docs/` (`01_guides`, `02_reference`, `03_examples`, structured like a content folder: `NN_name/page.md` with a `title` in the front matter) – update it together with code changes.

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
- `Blueprint` – `site/blueprints/{name}.{yml,yaml,php}`, falls back to core blueprints in `src/blueprints/`. Names are paths: `pages/article`, `site`, `users/editor`, `custom-fields/seo`. `Page::blueprint()`: `pages/{template}` → blueprint with `default: true` → `pages/default`; `User::blueprint()`: `users/{role}` (role defaults to `user`) → `users/user` → `users/default`; `Site::blueprint()`: `site`. Also `fields()`, `field()`, `tabs()`, `defaults()`, `validate($data)`, `toArray()`.
- `BlueprintNormalizer` – raw props → tabs → columns → fields (Kirby v6 style: root `fields` are definitions referenced by name from tabs/columns when a layout exists; sections → fields; groups spliced; `extends`; string shortcuts). Invalid fields become `info` error fields instead of exceptions.
- `Field` – a blueprint field definition (`name`, `type`, props, `validate()`).
- `FieldValue` – field methods of models (`HasFields` trait on `Page`, `Site`, `User`, `Values`): `$page->createdAt()` is formatted + escaped by field type when echoed; other calls go to Carbon/Collection/Stringable (string results are wrapped again, so they stay escaped). Raw values stay in `data()`. Model methods win over field names, use `field("url")`. `Values` = structure rows / object fields.
- Global helpers in `src/helpers.php`: `page()`, `site()`, `user()`, `md()`, `esc()`, `dd()`, `redirect()`, `partial()` (`site/templates/partials/`), `static_file_url()` (mtime from `public/`).
- Templates get `$page` and `$site`; field methods are already escaped (`esc()` doesn't double-escape them), raw `data()` must be `esc()`ed, `md()` for markdown.

## Guidelines

Modern PHP, keep it simple, flat class structure, camelCase, self-explaining names, comments only where code isn't obvious, English. Breaking changes are fine until a stable release.

## Not implemented yet

Panel (Vue + Vite), blocks/fieldsets normalization, file models, plugins, controllers, models, routes in `site/`, sqlite/postgres.
