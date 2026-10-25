---
title: "Layout: tabs, columns, fields"
---
Every blueprint is normalized into the same structure: **tabs → columns → fields**.

```php
$blueprint->tabs();
// [
//   "content" => [
//     "name" => "content", "label" => "Content", "icon" => null,
//     "columns" => [
//       ["width" => "2/3", "fields" => ["intro" => [...], "articles" => [...]]],
//     ],
//   ],
// ]
```

## Field definitions and references

When a blueprint has a layout (`tabs`, `columns` or `sections`), root `fields` are **definitions**. They only appear in the panel and in `$blueprint->fields()` when a tab or column references them:

```yaml
fields:
  title:
    type: text
  date:
    type: date

tabs:
  content:
    fields:
      - title             # reference
      - date
      - text:             # inline definition
          type: textarea
```

Without a layout, root `fields` are the layout themselves.

## Tabs

```yaml
tabs:
  content:
    label: Content      # default: the name, "seoSettings" → "Seo settings"
    icon: page
    columns: [...]      # or `fields`, wrapped in one 1/1 column
  seo: tabs/seo         # extends site/blueprints/tabs/seo.yml
  old: false            # removes an (inherited) tab
```

## Columns

```yaml
columns:
  - width: 2/3          # default 1/1
    fields: [title, text]
  - width: 1/3
    fields: [date]
```

`columns` at the root level are wrapped in a `main` tab.

## Field shortcuts

| Shortcut | Meaning |
|---|---|
| `- title` | reference to the root field definition `title` |
| `text: true` | field `text` of type `text` (the name is the type) |
| `title: text` | field `title` of type `text` |
| `seo: custom-fields/seo` | extends a blueprint file |
| `old: false` | removes an (inherited) field |

## Groups

A `group` field is replaced by its fields. `when` of the group is passed to every field:

```yaml
fields:
  address:
    type: group
    when:
      hasAddress: true
    fields:
      street: text
      city: text
```

## Sections (legacy)

Kirby style `sections` are converted into fields:

| Section type | Field type |
|---|---|
| `fields` | its fields are added directly |
| `pages` | `pagelist` |
| `files` | `filelist` |
| `info` | `info` |
| `stats` | `stats` |

`headline` becomes `label`.

## Field names

All fields of a blueprint share one namespace (case insensitive). Duplicates, unknown types and missing references don't break the blueprint, they are replaced with a red `info` field describing the error.
