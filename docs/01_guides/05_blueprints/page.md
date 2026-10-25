---
title: Blueprints
---
A blueprint describes the **data structure** of a model (its fields) and its **panel view** (tabs → columns → fields). Blueprints are yaml files (or php files returning an array) in `site/blueprints/`.

| Model | Blueprint |
|---|---|
| page | `site/blueprints/pages/{template}.yml` |
| site | `site/blueprints/site.yml` |
| user | `site/blueprints/users/{role}.yml` |
| file | `site/blueprints/files/{template}.yml` |

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

All [layout rules](../../02_reference/01_blueprints/01_layout/page.md) and [field types](../../02_reference/02_fields/page.md).

## Which blueprint does a page use?

The page's `template` decides:

1. `site/blueprints/pages/{template}.yml`
2. the page blueprint with `default: true`
3. `site/blueprints/pages/default.yml`
4. the core [`src/blueprints/pages/default.yml`](../../../src/blueprints/pages/default.yml)

```md
---
title: Hello world
template: article
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

See [Reusable fields](../../03_examples/02_reusable-fields/page.md).

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

Classes: [`Blueprint`](../../../src/Blueprint.php), [`BlueprintNormalizer`](../../../src/BlueprintNormalizer.php), [`Field`](../../../src/Field.php).

## Planned: fields as methods

Every field of a blueprint will become a method of its model, returning a formatted and escaped value object (built on illuminate/support):

```php
<?= $page->createdAt() ?>                      <!-- formatted & escaped -->
<?= $page->createdAt()->format("Y.m.d") ?>
<?php if ($page->createdAt()->isEmpty()): ?>
```

The raw value always stays available in `$page->data()->createdAt`.
