---
title: Blocks
sort: 6
---
Block types (fieldsets) of `blocks` and `layout` fields are blueprints in `site/blueprints/blocks/{type}.yml`. Core block types (`src/blueprints/blocks/`): `heading`, `text`, `list`, `image`, `gallery`, `quote`, `code`, `markdown`, `video`, `line`.

```yaml
# site/blueprints/blocks/callout.yml
title: Callout
icon: info
fields:
  text:
    type: textarea
    required: true
  theme:
    type: select
    options: [info, warning]
```

## fieldsets

```yaml
fields:
  body:
    type: blocks
    fieldsets: [heading, text, image, callout]

  sidebar:
    type: blocks
    fieldsets:
      text: true
      teaser:                 # inline block type
        title: Teaser
        fields:
          link: pagepicker

  content:
    type: blocks
    fieldsets:
      text:
        type: group
        label: Text
        fieldsets: [heading, text, list]
      media:
        type: group
        label: Media
        fieldsets: [image, gallery, video]
```

Without `fieldsets`, all core block types are available. A block type can't contain itself.

## Layouts

```yaml
fields:
  page:
    type: layout
    layouts:
      - "1/1"
      - "1/2, 1/2"
      - "1/3, 2/3"
    fieldsets: [heading, text, image]
```

## Content format

Blocks and layouts are stored like in Kirby:

```json
{
  "body": [
    {"id": "…", "type": "heading", "isHidden": false, "content": {"level": "h2", "text": "Hello"}}
  ],
  "page": [
    {"id": "…", "attrs": {}, "columns": [
      {"id": "…", "width": "1/2", "blocks": [ … ]}
    ]}
  ]
}
```

## Rendering

```php
<?= $page->body() ?>          <!-- all visible blocks -->
<?= $page->page() ?>          <!-- all layout rows -->

<?php foreach ($page->body()->toBlocks() as $block): ?>
  <?php if ($block->type() === "heading"): ?>…<?php endif ?>
  <?= $block ?>
<?php endforeach ?>
```

Each block is rendered with `site/snippets/blocks/{type}.php`, falling back to the core snippets in `src/views/blocks/`. Snippets get `$block` (field methods for its content, `id()`, `type()`, `isHidden()`), `$page` and `$site`. Layout rows use `blocks/layout.php` with `$layout`.

```php
<?php // site/snippets/blocks/callout.php ?>
<aside class="callout callout-<?= $block->theme()->or("info") ?>">
  <?= $block->text() ?>
</aside>
```

Validation checks the content of every visible block, errors use paths like `body.2.text` (layouts: `page.0.1.2.text` for row, column, block).
