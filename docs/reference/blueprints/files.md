---
title: File blueprints
sort: 5
---
`site/blueprints/files/{template}.yml` – the data (meta) of files, stored in `{filename}.json` next to the file. The file's `template` chooses the blueprint, falls back to `files/default` (core: an `alt` field). Core also has `files/image`.

```yaml
title: Image
accept:
  mime: image/*
  extension: [jpg, png, webp]
  maxsize: 5000000
fields:
  alt:
    type: text
  caption:
    type: textarea
```

`accept` is checked when files are uploaded: `mime` (patterns like `image/*`, `accept: image/*` is a shortcut), `extension` and `maxsize` (bytes). Uploads get their template from the field's `uploads` (`uploads: image` or `uploads: {template: image}`).

Options: `changeName`, `changeTemplate`, `create`, `delete`, `read`, `replace`, `sort`, `update`.

```php
$file = $page->file("cover.jpg");

$file->url();          // /blog/cover.jpg
$file->alt();          // field method
$file->dimensions();   // ["width" => 1200, "height" => 800]
```

Files with `.md`/`.php` (subpages) and executable or server config extensions can't be uploaded. Html, svg and other active formats are served as downloads.
