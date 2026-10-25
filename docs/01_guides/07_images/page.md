---
title: Images
---
Content images (files of pages) can be converted to [AVIF](https://en.wikipedia.org/wiki/AVIF) automatically: smaller files, same look. The optimizer is based on the one of [minimaxcms](https://github.com/pstaender/minimaxcms) and needs Imagick or GD with AVIF support (`php -r 'var_dump(gd_info()["AVIF Support"] ?? false);'`). Without it, images are used as they are.

## For all pages

```php
<?php // config/config.php
return [
    "optimizeContentImages" => true,
];
```

Every local `<img>` in the rendered html of a page (`/blog/hello.jpg`) now points to its optimized version, e.g. `/media/images/7abed….avif`.

## In templates

```php
<img src="<?= esc(optimized_content_image($page->cover()->toFile())) ?>" alt="">
<img src="<?= esc(optimized_content_image("/blog/hello.jpg", maxFileSize: 200)) ?>" alt="">
<?= optimized_content_images(md($page->data()->content), quality: 60) ?>
```

| Helper | |
|---|---|
| `optimized_content_image($file, $maxFileSize = null, $quality = null)` | the url of the optimized image; `$file` is a [`File`](../../../src/File.php), an url (`/blog/hello.jpg`), a path (`blog/hello.jpg`), a reference (`file://uid`) or a filename of the current page |
| `optimized_content_images($html, $maxFileSize = null, $quality = null)` | the html with all local `<img>` optimized |

- `maxFileSize` is a rough target in KB: the quality is lowered (down to 30), then the image is scaled down (not below 640px).
- `quality` is 0–100. Without both (auto mode), the quality depends on the resolution (75 for small images down to 50 for 8 megapixels) and the target is 100 KB; WebP/AVIF images below that are kept.
- Only `jpg`, `png`, `gif`, `webp`, `avif` and `bmp` are converted, and only [used files](../02_content/page.md#files) (unused files must not become public through the optimizer). Otherwise the file's own url is returned.

## Cache

Optimized images are stored in `public/media/images/{hash}.avif` (gitignored). The hash covers the image content and the settings, so a changed image gets a new url and stale files are never served. To remove old files:

```sh
bin/blueprint media:clear
```

Source: [`src/ImageOptimizer.php`](../../../src/ImageOptimizer.php), [`src/GdImageFile.php`](../../../src/GdImageFile.php), [`src/ImagickImage.php`](../../../src/ImagickImage.php).
