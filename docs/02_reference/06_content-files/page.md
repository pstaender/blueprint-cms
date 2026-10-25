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

The special data key `template` chooses the template and the [blueprint](../01_blueprints/02_pages/page.md).

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
