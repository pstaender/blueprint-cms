---
title: Blueprints
sort: 1
---
Source: `src/Blueprint.php`, `src/BlueprintNormalizer.php`

## Files and names

A blueprint is named by its path relative to `site/blueprints/`, without extension:

| Name | File |
|---|---|
| `pages/article` | `site/blueprints/pages/article.yml` |
| `site` | `site/blueprints/site.yml` |
| `users/editor` | `site/blueprints/users/editor.yml` |
| `custom-fields/seo` | `site/blueprints/custom-fields/seo.yml` |

Extensions are tried in this order: `.yml`, `.yaml`, `.php`. A php blueprint returns an array and may contain lambdas.

If a blueprint is missing in `site/blueprints/`, the core blueprint in `src/blueprints/` is used (`pages/default`, `site`, `users/default`, `files/default`).

## Common props

| Prop | Description |
|---|---|
| `title` | Name of the blueprint, defaults to the file name (`seo-page` → "Seo page") |
| `extends` | Blueprint name(s) to inherit from, see below |
| `fields` | Field definitions, see [Fields](/reference/fields/) |
| `tabs`, `columns`, `sections` | The panel layout, see [Layout](/reference/blueprints/layout) |
| `options` | Permissions, see the model pages |
| `icon`, `image`, … | Any other prop is kept and passed to the panel |

## extends

`extends` merges another blueprint into the current one. Maps are merged recursively, lists and values are replaced. The current blueprint wins.

```yaml
extends: pages/article
title: News article
```

Several blueprints: `extends: [pages/article, custom-fields/meta]`.

`extends` works on blueprints, tabs, sections and fields. A string instead of props is a shortcut for `extends`, unless it is a field type:

```yaml
fields:
  seo: custom-fields/seo   # same as {extends: custom-fields/seo}
  title: text              # same as {type: text}
```

`false` removes a field (or tab) that the blueprint inherits, also when the layout references it:

```yaml
extends: pages/article
fields:
  category: false
```

## Loading

```php
use Blueprint\Blueprint;

Blueprint::load("pages/article");                  // throws if not found
Blueprint::load("pages/article", "pages/default"); // first existing one
Blueprint::find("pages/article");                  // raw props or null
Blueprint::names("pages");                         // ["pages/article", "pages/default", …]

$page->blueprint();
site()->blueprint();
user()->blueprint();
```

Raw blueprint files are cached per request.
