---
title: Page blueprints
sort: 2
---
`site/blueprints/pages/{type}.yml` – chosen by the page's `type` (default `page`), see [Blueprints guide](/guides/blueprints).

```yaml
title: Article
icon: 📝
default: true      # fallback for pages without an own blueprint

status:
  draft: In progress
  listed: true
  unlisted: false

options:
  delete: false
  changeSlug:
    admin: true
    "*": false

fields:
  title:
    type: text
    required: true
  date:
    type: date
```

## default

`default: true` marks the blueprint that is used when no `pages/{type}` blueprint exists. Lookup:

1. `pages/{type}`
2. the first page blueprint with `default: true`
3. `pages/default` (site, then core)

`page` and `default` are one type in the panel (`page`), unless both `pages/page.yml` and `pages/default.yml` exist in `site/blueprints`.

## status

The status of a page is defined by its folder name:

| Status | Folder | |
|---|---|---|
| `draft` | `.about` | only visible for logged in users |
| `unlisted` | `about` | public, not in navigations |
| `listed` | `01_about` | public, sorted by number |

`status` defines which ones are available in the panel. `true` uses the default label, a string is the label, a map can set `label` and `text`, `false` disables it. `draft` always exists. Without `status`, all three are available.

## options

| Option | |
|---|---|
| `changeSlug`, `changeStatus`, `changeTemplate`, `changeTitle` | |
| `create`, `delete`, `duplicate`, `move`, `sort`, `update` | |
| `preview`, `read` | |

`options: false` disables all of them, `true` (default) keeps the defaults. Values are booleans or maps of user roles (`"*"` = all other roles). `null` means not set (default behaviour).
