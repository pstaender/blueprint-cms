---
title: Page builder
sort: 5
---
A landing page with layout rows, a custom block type and images.

```yaml
# site/blueprints/pages/landing.yml
title: Landing page

fields:
  title: text
  sections:
    type: layout
    layouts: ["1/1", "1/2, 1/2", "2/3, 1/3"]
    fieldsets: [heading, text, image, cta]
  cover:
    type: filepicker
    max: 1
    accept: image/*
    uploads: image
  seo: custom-fields/seo

tabs:
  content:
    fields: [title, sections]
  settings:
    fields: true          # cover and the seo fields
```

```yaml
# site/blueprints/blocks/cta.yml
title: Call to action
fields:
  label:
    type: text
    required: true
  link:
    type: pagepicker
    max: 1
```

```php
<?php // site/snippets/blocks/cta.php ?>
<?php if ($target = $block->link()->toPage()): ?>
  <a class="button" href="<?= esc($target->url()) ?>"><?= $block->label() ?></a>
<?php endif ?>
```

```php
<?php // site/templates/landing.php ?>
<?= partial("head") ?>
<?php if ($cover = $page->cover()->toFile()): ?>
  <img src="<?= esc($cover->url()) ?>" alt="<?= $cover->alt() ?>">
<?php endif ?>
<h1><?= $page->title() ?></h1>
<?= $page->sections() ?>
<?= partial("footer") ?>
```

The page uses this blueprint and template with `type: landing` in its content file (or by choosing the type in the panel: page settings → Type).
