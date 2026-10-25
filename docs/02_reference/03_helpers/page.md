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
| `md($markdown)` | markdown (Markdown Extra) to html |
| `partial("head", $vars = [], $return = false)` | renders `site/templates/partials/head.php` |
| `static_file_url("/assets/app.css")` | `/assets/app.css?1700000000` (modification time of the file in `public/`) |
| `redirect("/test", 302)` | stops rendering and redirects |
| `dd(...$values)` | dumps the values and stops |

```php
<link rel="stylesheet" href="<?= esc(static_file_url("/assets/css/layout.css")) ?>">
<h1><?= esc(page()->title()) ?></h1>
<?= md(page()->data()->content) ?>
```
