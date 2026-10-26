---
title: Blueprints
sort: 5
---
A blueprint describes the **data structure** of a model (its fields) and its **panel view** (tabs → columns → fields). Blueprints are yaml files (or php files returning an array) in `site/blueprints/`.

| Model | Blueprint |
|---|---|
| page | `site/blueprints/pages/{type}.yml` |
| site | `site/blueprints/site.yml` |
| user | `site/blueprints/users/{role}.yml` |
| file | `site/blueprints/files/{template}.yml` |
| block | `site/blueprints/blocks/{type}.yml` |

## Fields first, layout second

Fields are defined once at the root of the blueprint. Tabs and columns only reference them by name:

```yaml
# site/blueprints/pages/blog.yml
title: Blog

fields:
  intro:
    type: textarea
  articles:
    type: pagelist
    template: article
  cover:
    type: filepicker
    max: 1
  seoTitle:
    label: SEO title
    type: text

tabs:
  content:
    columns:
      - width: 2/3
        fields:
          - intro
          - articles
      - width: 1/3
        fields:
          - cover
  seo:
    label: SEO
    fields:
      - seoTitle
```

Without `tabs` (or `columns`), the fields are the layout: they are put in one column in a `main` tab.

```yaml
# site/blueprints/pages/article.yml
title: Article
fields:
  title:
    type: text
  date:
    type: date
```

`fields: true` in a tab or column shows all fields that aren't placed anywhere else.

All [layout rules](/reference/blueprints/layout) and [field types](/reference/fields/).

## Which blueprint does a page use?

The page's `type` decides (default: `page`):

1. `site/blueprints/pages/{type}.yml`
2. the page blueprint with `default: true`
3. `site/blueprints/pages/default.yml`
4. the core `src/blueprints/pages/default.yml`

```md
---
title: Hello world
type: article
---
```

## Reusing fields

Put shared fields in `site/blueprints/custom-fields/` and use them with `extends` (or the short string form):

```yaml
# site/blueprints/custom-fields/seo.yml
type: group
fields:
  seoTitle:
    type: text
  seoDescription:
    type: textarea
```

```yaml
fields:
  seo: custom-fields/seo
```

See [Reusable fields](/examples/reusable-fields).

## Using blueprints in code

```php
$blueprint = $page->blueprint();      // Blueprint\Blueprint

$blueprint->title();                  // "Blog"
$blueprint->fields();                 // ["intro" => Field, …]
$blueprint->field("cover")->type;     // "filepicker"
$blueprint->tabs();                   // normalized tabs → columns → fields
$blueprint->defaults();               // default values of all fields
$blueprint->validate($page->data());  // ["title" => ["Title is required"]]
$blueprint->toArray();                // everything, e.g. as JSON for the panel
```

Classes: `Blueprint`, `BlueprintNormalizer`, `Field`.

## Fields as methods

Every field of a blueprint is a method of its model, returning a formatted and escaped value:

```php
<?= $page->createdAt() ?>                      <!-- formatted & escaped -->
<?= $page->createdAt()->format("Y.m.d") ?>
<?php if ($page->createdAt()->isEmpty()): ?>
```

The raw value stays available in `$page->data()->createdAt`. See [Field methods](/reference/fields/field-methods).
