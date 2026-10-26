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

## subpages

Every page blueprint gets a `subpages` field, the list of the page's subpages in the panel (a [`pagelist`](/reference/fields/page) in a full width column at the end of the first tab). It isn't added if the blueprint has its own `pagelist` field or a field named `subpages`, which can also change it:

```yaml
fields:
  subpages: false            # no subpages list

  # or a changed one
  subpages:
    type: pagelist
    layout: cards
```

The site blueprint gets the same field (labeled "Pages"), unless it has a `pagelist`.

## icon

`icon` is shown next to the pages of this type in the panel: the name of a panel icon (`icon: image`), any text like an emoji (`icon: 📝`) or an inline image (`icon: data:image/svg+xml;base64,…`), see [Panel guide](/guides/panel#icons).

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

`status` defines which ones are available in the panel. `true` uses the default label, a string is the label, a map can set `label` and `text`, `false` disables it, except for `draft`: every page (but the home page) can be taken offline as a draft. Without `status`, all three are available.

## options

| Option | |
|---|---|
| `changeSlug`, `changeStatus`, `changeTemplate`, `changeTitle` | |
| `create`, `delete`, `duplicate`, `move`, `sort`, `update` | |
| `preview`, `read` | |

`options: false` disables all of them, `true` (default) keeps the defaults. Values are booleans or maps of user roles (`"*"` = all other roles). `null` means not set (default behaviour).
