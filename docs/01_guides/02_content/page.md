---
title: Content
---
Everything in `content/` is a page, addressed by its path.

## Pages

A page is either a folder with a content file or a single file:

| File | Url |
|---|---|
| `content/home.md` or `content/home/page.md` | `/` |
| `content/imprint/page.md` | `/imprint` |
| `content/01_blog/page.php` | `/blog/` |
| `content/01_blog/01_newest_entry/page.json` | `/blog/newest_entry` |

- `01_blog` → slug `blog`, sort number `1`. Pages with a number are *listed* (e.g. in navigations), pages without are *unlisted*.
- A leading `.` (`.secret/`) marks a *draft*, only visible for logged in users.
- A page with published subpages has a trailing slash (`/blog/`), all other pages don't. The other variant redirects with 301.
- A folder wins over a file with the same slug.

## Content files

Data of a folder page is merged in this order (later wins):

1. `page.json`
2. `page.md` – yaml front matter, the markdown body becomes `content`
3. `page.content.md` – becomes `content`

```md
---
title: About us
template: article
tags: [team, company]
---
## We are …
```

```php
<?= esc($page->data()->title) ?>
<?= md($page->data()->content) ?>
<?= esc($page->data()->tags[0]) ?>
```

`page.php` is executed when the page is rendered, see [Templates](../03_templates/page.md).

## Site, users and redirects

- `content/site.json` – site data, `site()->data()->name`
- `content/users.json` – list of users (`email`, `uid`, `role`, …)
- `content/redirects.json` – `{"301": {"from-foo": "to-bar"}}`

See [Content files](../../02_reference/06_content-files/page.md).
