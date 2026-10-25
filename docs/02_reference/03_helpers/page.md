---
title: Helpers
---
Global functions for templates, defined in [`src/helpers.php`](../../../src/helpers.php).

| Helper | Description |
|---|---|
| `page()` | the current page |
| `page("blog/newest_entry")` | a page by its path, `null` if not found |
| `site()` | the site, data from `content/site.json` |
| `user()` | the logged in user or `null` |
| `user("admin")` | a user by email/username or uid |
| `esc($value)` | escapes for html output |
| `md($markdown, $page = null)` | markdown (Markdown Extra) to html, relative file links (`hello.jpg`, `blog/hello.jpg`) point to the files of `$page` (default: the current page) |
| `partial("head", $vars = [], $return = false)` | renders `site/templates/partials/head.php` |
| `snippet("head", $vars = [], $return = false)` | the same as `partial()`, for Kirby compatibility |
| `static_file_url("/assets/app.css")` | `/assets/app.css?1700000000` (modification time of the file in `public/`) |
| `redirect("/test", 302)` | stops rendering and redirects |
| `dd(...$values)` | dumps the values and stops |
| `optimized_content_image($file, $maxFileSize = null, $quality = null)` | url of the image converted to AVIF, see [Images](../../01_guides/07_images/page.md) |
| `optimized_content_images($html, $maxFileSize = null, $quality = null)` | the html with all local images converted to AVIF |

```php
<link rel="stylesheet" href="<?= esc(static_file_url("/assets/css/layout.css")) ?>">
<h1><?= esc(page()->title()) ?></h1>
<?= md(page()->data()->content) ?>
```
