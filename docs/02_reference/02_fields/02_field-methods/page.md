---
title: Field methods
---
Every field is a method of its model (page, site, user, structure row, object field). It returns a [`FieldValue`](../../../../src/FieldValue.php):

```php
<?= $page->createdAt() ?>                       // formatted & escaped
<?= $page->createdAt()->format("Y.m.d") ?>      // Carbon, still escaped
<?php if ($page->createdAt()->isEmpty()): ?>
<?= $page->data()->createdAt ?>                 // raw value – escape it yourself
```

The field definition comes from the model's [blueprint](../../01_blueprints/page.md). Data that isn't defined in the blueprint works too: it is treated as `text` (or as a list for arrays).

Model methods win over fields with the same name (e.g. `url`, `type`, `children`). Use `field()` for those:

```php
<?= $page->field("url") ?>
```

`$page->title()` is a field method too, it falls back to the slug.

## Output

Echoing a field value (or `esc($value)`, which doesn't escape it twice) outputs:

| Type | Output |
|---|---|
| `text`, `email`, `url`, `number`, … | escaped value |
| `textarea` | markdown as html, inline html in the markdown is escaped (use `md($page->data()->text)` to allow html); relative file links like `![](hello.jpg)` point to the page's files |
| `writer`, `list` | the html as it is (trusted editor content) |
| `date` | `Y-m-d`, `Y-m-d H:i` with `time: true`, or the field's `format` (PHP date format) |
| `datetime` | `Y-m-d H:i` or `format`, also `createdAt()` / `modifiedAt()` of pages, files and users (unless their blueprint defines these fields) |
| `time` | `H:i` or `format` |
| `toggle` | `true` / `false` |
| `select`, `radio`, `toggles`, `checkboxes`, `multiselect`, `tags` | option labels, comma separated |
| `pagepicker` / `userpicker` | page titles / user names (or emails), comma separated |
| `filepicker` | file urls, comma separated |
| `blocks` | the rendered blocks (`site/snippets/blocks/{type}.php`), hidden blocks are skipped |
| `layout` | the rendered rows (`site/snippets/blocks/layout.php`) |
| `password`, `structure`, `object` | nothing, use the methods below |

```yaml
publishedAt:
  type: date
  format: d.m.Y
```

## Methods

| Method | |
|---|---|
| `value()` | the raw value |
| `isEmpty()`, `isNotEmpty()` | `null`, `""`, whitespace and `[]` are empty |
| `or($fallback)` | fallback for empty values: `$page->seoTitle()->or($page->title())` |
| `toString()` | the formatted output |
| `toBool()`, `toInt()`, `toFloat()` | |
| `toDate()` | `Illuminate\Support\Carbon` or `null` |
| `toArray()` | list, comma separated strings are split |
| `toCollection()` | `Collection` of `FieldValue`s (structure: of `Values`) |
| `toValues()` | nested fields of an object field |
| `toPages()`, `toPage()` | pages of a `pagepicker` (`page://uid` or page paths) |
| `toUsers()`, `toUser()` | users of a `userpicker` (`user://uid`, emails or uids) |
| `toFiles()`, `toFile()` | [`File`](../../../../src/File.php)s of a `filepicker` (`file://uid`, filenames of the page or `blog/cover.jpg`) |
| `toBlocks()` | `Collection` of [`Block`](../../../../src/Block.php)s |
| `toLayouts()` | `Collection` of [`Layout`](../../../../src/Layout.php)s |
| `labels()` | option labels of the selected values |
| `type()`, `field` | field type and definition ([`Field`](../../../../src/Field.php)) |

Field values are countable and iterable (`foreach ($page->tags() as $tag)`).

## illuminate/support

All other methods are passed to an [illuminate/support](https://laravel.com/docs/helpers) object:

| Type | Object |
|---|---|
| `date`, `time` | [`Carbon`](https://carbon.nesbot.com/docs/) |
| lists (`tags`, `checkboxes`, `structure`, pickers, arrays, …) | [`Collection`](https://laravel.com/docs/collections) |
| everything else | [`Stringable`](https://laravel.com/docs/strings#fluent-strings) |

```php
<?= $page->createdAt()->diffForHumans() ?>
<?= $page->title()->limit(20) ?>
<?php if ($page->createdAt()->isPast()): ?>
```

Returned strings are wrapped in a `FieldValue` again, so they stay escaped. Other results (bool, int, Carbon, Collection) are returned as they are. On empty values these calls return an empty `FieldValue`, so `$page->missing()->format("Y")` outputs nothing instead of failing.

## Structures and objects

```yaml
links:
  type: structure
  fields:
    label: text
    url: url
address:
  type: object
  fields:
    city: text
```

```php
<?php foreach ($page->links() as $link): ?>
  <a href="<?= $link->url() ?>"><?= $link->label() ?></a>
<?php endforeach ?>

<?= $page->address()->city() ?>
```

Rows and objects are [`Values`](../../../../src/Values.php): they have the same field methods, `field($name)` and `data()`.
