---
title: Content
sort: 2
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
type: article
tags: [team, company]
---
## We are …
```

```php
<?= esc($page->data()->title) ?>
<?= md($page->data()->content) ?>
<?= esc($page->data()->tags[0]) ?>
```

`page.php` is executed when the page is rendered, see [Templates](/guides/templates).

`type` chooses the [blueprint](/guides/blueprints) and the [template](/guides/templates) of the page (default: `page`). Every write (panel or PHP) fills the hidden fields `uid`, `createdAt` (first write) and `modifiedAt` (last write, ISO 8601 datetimes) of pages, files and users (the site only gets the dates); they can't be changed. Pages stored in markdown (`about.md`, `page.md`) don't get them: their dates are the file's modification time (also `createdAt`, PHP can't read creation times), a `uid` or `createdAt` in the front matter is kept. Without a uid they are referenced by their path. References use the uid (`page://uid`), so they survive renamed slugs. In templates the dates work like datetime fields: `<?= $page->modifiedAt() ?>` (`2026-10-25 17:30`), `$page->modifiedAt()->format("d.m.Y")`. They are hidden in the panel, unless a blueprint shows them as read-only fields:

```yaml
fields:
  createdAt:
    label: Created
    type: datetime
    disabled: true
  modifiedAt:
    label: Modified
    type: datetime
    disabled: true
```

 Existing content gets uids with `bin/blueprint content:uids`.

## Files

Other files in a page folder belong to the page, their data lives in `{filename}.json`:

```
content/01_blog/
  page.md
  cover.jpg          → /blog/cover.jpg
  cover.jpg.json     {"uid": "…", "createdAt": "…", "modifiedAt": "…", "alt": "…"}
```

Files are only public when they are used in the content, e.g. as `![hello](hello.jpg)` in a textarea (relative links are resolved to `/blog/hello.jpg`) or picked in a `filepicker`. In the panel, drag a file from a file list (or from your computer) into a textarea or writer field to insert such a link. Links in markdown may also use references: `[About](page://uid)`, `[Report](file://uid)` point to the page or file even after its slug changed (the writer field inserts page links like that).

Images (except svg), audio, video, PDFs and plain text files are shown in the browser, all other files (also svg, html and javascript) are downloads (`application/octet-stream`), so an uploaded file can't run scripts on the site.

```php
<?php foreach ($page->files() as $file): ?>
  <img src="<?= esc($file->url()) ?>" alt="<?= $file->alt() ?>">
<?php endforeach ?>
```

## Site, users and redirects

- `content/site.json` – site data, `site()->data()->name`
- `content/users.json` – list of users (`email`, `uid`, `role`, …)
- `content/redirects.json` – `{"301": {"from-foo": "to-bar"}}`

See [Content files](/reference/content-files).
