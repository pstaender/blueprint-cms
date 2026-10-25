---
title: Fields
---
Source: [`src/Field.php`](../../../src/Field.php). Field types and options follow the [Kirby field reference](https://getkirby.com/docs/reference/panel/fields), all props are passed to the panel.

## Common props

| Prop | Description |
|---|---|
| `type` | Field type, defaults to the field name (`text: true`) |
| `label` | Defaults to the name: `seoTitle` → "Seo title" |
| `width` | `1/1` (default), `1/2`, `1/3`, `2/3`, `1/4`, `3/4` |
| `default` | Default value, see `$blueprint->defaults()` |
| `required` | Value must not be empty |
| `help` | Help text below the field |
| `placeholder` | Placeholder of inputs |
| `disabled` | Not editable |
| `when` | Only show the field if other fields match, e.g. `when: {hasCover: true}` |
| `translate` | `false` for values shared between languages |
| `extends` | Inherit props from a blueprint file, e.g. `custom-fields/seo` |

[Validation props](01_validation/page.md): `required`, `min`, `max`, `minlength`, `maxlength`, `pattern`.

```php
$field = $page->blueprint()->field("date");

$field->name;            // "date"
$field->type;            // "date"
$field->label();         // "Date"
$field->get("display");  // any prop, dot notation
$field->hasValue();      // false for display fields like `info` or `pagelist`
$field->validate("x");   // ["Date is not a valid date value"]
```

## Text

| Type | | Main options |
|---|---|---|
| `text` | single line text | `minlength`, `maxlength`, `pattern`, `counter`, `converter` |
| `textarea` | multi line markdown | `buttons`, `size` (`small`, `medium`, `large`, `huge`), `files` |
| `writer` | inline rich text (html) | `marks`, `nodes`, `inline` |
| `email` | email address | |
| `url` | url | |
| `tel` | phone number | |
| `password` | password | |
| `slug` | url-friendly text | `sync`, `wizard` |
| `hidden` | value without input | |

## Numbers, dates & colors

| Type | | Main options |
|---|---|---|
| `number` | number | `min`, `max`, `step` |
| `range` | slider | `min`, `max`, `step` |
| `date` | date (and time) | `display`, `time`, `min`, `max` |
| `datetime` | date and time (same as `date` with `time: true`) | `display`, `min`, `max` |
| `time` | time | `display`, `step` |
| `color` | color | `format`, `alpha`, `options` |

## Choices

| Type | | Main options |
|---|---|---|
| `toggle` | on/off | `text` |
| `select` | dropdown, one value | `options` |
| `radio` | one value | `options`, `columns` |
| `toggles` | button group, one value | `options`, `labels`, `icons` |
| `checkboxes` | several values | `options`, `columns`, `min`, `max` |
| `multiselect` | several values | `options`, `min`, `max`, `search` |
| `tags` | free tags | `options`, `accept`, `min`, `max` |

`options` is a list (`[news, review]`), a map (`{news: News, review: Review}`), a list of `{value, text}` maps, or dynamic (`options: {type: query, …}`, not validated).

## Pickers & lists

| Type | | Main options |
|---|---|---|
| `pagepicker` | select pages, stores `page://uid` | `min`, `max`, `multiple` |
| `filepicker` | select (sortable) and upload files of the page, stores `file://uid` | `accept` (e.g. `image/*`), `uploads` (file template), `min`, `max`, `multiple` |
| `userpicker` | select users, stores `user://uid` | `min`, `max`, `multiple` |
| `link` | link to a page, file, url, email, … | `options` |
| `pagelist` | subpages of the model (display only), sortable | `template` (page type filter), `layout` (`list`, `cards`, `table`), `status` |
| `filelist` | files of the page with uploads (display only) | `template` (file template filter), `layout`, `uploads` |

Legacy names: `pages` → `pagepicker`, `files` → `filepicker`, `users` → `userpicker`.

## Structured data

| Type | | Main options |
|---|---|---|
| `structure` | list of rows with nested `fields` | `fields`, `columns`, `min`, `max`, `sortBy` |
| `object` | one map with nested `fields` | `fields` |
| `entries` | list of single values | `field`, `min`, `max` |
| `list` | html list | `marks` |
| `blocks` | block editor, see [Blocks](../01_blueprints/06_blocks/page.md) | `fieldsets`, `min`, `max` |
| `layout` | rows of columns with blocks | `layouts` (e.g. `["1/1", "1/2, 1/2"]`), `fieldsets` |

```yaml
links:
  type: structure
  fields:
    label: text
    url:
      type: url
      required: true
```

## Display (no value)

| Type | |
|---|---|
| `headline` | heading |
| `info` | text box, `text`, `theme` (`info`, `positive`, `negative`, `notice`) |
| `line` | horizontal line |
| `gap` | empty space |
| `stats` | key figures, `reports` |
| `buttons` | buttons |

## Group

`group` is no field, it is replaced by its `fields`, see [Layout](../01_blueprints/01_layout/page.md#groups).
