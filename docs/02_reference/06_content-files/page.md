---
title: Content files
---
## Page files

| File | |
|---|---|
| `page.json` | data |
| `page.md` | yaml front matter as data, body as `content` |
| `page.content.md` | `content` (markdown) |
| `page.php` | renders the page, or returns data |
| `{slug}.md`, `{slug}.php` | single file page |

Special data keys:

| Key | |
|---|---|
| `type` | the page type, chooses the [blueprint](../01_blueprints/02_pages/page.md) and the template (default `page`) |
| `uid` | unique id, added by the panel (or `bin/blueprint content:uids`), used for references like `page://uid` |

## Files

All other files in a page folder are the page's files (except `.md`/`.php`, which are subpages, and hidden files). Their data lives next to them in `{filename}.json`:

```
content/01_blog/
  page.md
  cover.jpg
  cover.jpg.json      {"uid": "k2c9…", "template": "image", "alt": "…"}
```

Files are served under the page url: `/blog/cover.jpg`. `template` chooses the [file blueprint](../01_blueprints/05_files/page.md).

## content/site.json

```json
{"name": "My website"}
```

## content/users.json

```json
[
  {"email": "admin", "uid": "19qiajkmca", "role": "admin", "password": "$2y$12$…"}
]
```

`password` is a `password_hash()`, set it with `bin/blueprint user:password admin`.

## content/redirects.json

```json
{
  "301": {
    "from-foo": "to-bar",
    "old": "https://example.com/new"
  }
}
```
