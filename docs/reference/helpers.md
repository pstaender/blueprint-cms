---
title: Helpers
sort: 3
---
Global functions for templates, defined in `src/helpers.php`.

| Helper | Description |
|---|---|
| `page()` | the current page |
| `page("blog/newest_entry")` | a page by its path, `null` if not found |
| `site()` | the site, data from `content/site.json` |
| `user()` | the logged in user or `null` |
| `user("admin")` | a user by email/username or uid |
| `esc($value)` | escapes for html output |
| `csp_nonce()` | the nonce for inline scripts of the current request: `<script nonce="<?= csp_nonce() ?>">`, see [Content Security Policy](/reference/config#content-security-policy) |
| `md($markdown, $page = null, $html = false)` | markdown to html (Markdown Extra with the config `markdown.extra`), inline html is escaped and links with unsafe schemes like `javascript:` point to `#`; `html: true` allows inline html, only for content that panel users can't edit (it may contain scripts), relative file links (`hello.jpg`, `blog/hello.jpg`) point to the files of `$page` (default: the current page), `page://uid` and `file://uid` links to the page or file |
| `partial("head", $vars = [])` | returns the output of `site/templates/partials/head.php` (or of an absolute path to a php file), throws if it doesn't exist: `<?= partial("head") ?>` |
| `snippet($name, $data = [], $return = false, $slots = false)` | renders `site/templates/snippets/{name}.php` like Kirby; `$name` can be a list (first existing), missing snippets render nothing |
| `endsnippet()`, `slot($name = "default")`, `endslot()` | snippets with slots, see [Templates](/guides/templates#snippets) |
| `static_file_url("/assets/app.css")` | `/assets/app.css?1700000000` (modification time of the file in `public/`) |
| `redirect("/test", 302)` | stops rendering and redirects |
| `dd(...$values)` | dumps the values and stops |
| `optimized_content_image($file, $maxFileSize = null, $quality = null)` | url of the image converted to AVIF, see [Images](/guides/images) |
| `optimized_content_images($html, $maxFileSize = null, $quality = null)` | the html with all local images converted to AVIF |

```php
<link rel="stylesheet" href="<?= esc(static_file_url("/assets/css/layout.css")) ?>">
<h1><?= esc(page()->title()) ?></h1>
<?= md(page()->data()->content) ?>
```
