---
title: Content files
sort: 6
---
## Page files

| File | |
|---|---|
| `page.json` | data |
| `page.md` | yaml front matter as data, body as `content` |
| `page.content.md` | `content` (markdown) |
| `page.php` | renders the page, or returns data |
| `{slug}.md`, `{slug}.php` | single file page |

A page uses one of three storage formats, changeable in the panel (page settings → Advanced → Storage format) or with `$page->changeContentFormat()`:

| Format | Files | |
|---|---|---|
| `json` | `page.json` | recommended: all data in one file |
| `md` | `page.md` | yaml front matter, the text (`content`) as markdown body |
| `json+md` | `page.json` + `page.content.md` | data as json, the text as markdown file |

Single file pages (`about.md`) use the `md` format, converting them to a json format turns them into a folder.

Special data keys:

| Key | |
|---|---|
| `type` | the page type, chooses the [blueprint](/reference/blueprints/pages) and the template (default `page`) |
| `uid` | unique id, used for references like `page://uid` |
| `createdAt` | ISO 8601 datetime of the first write |
| `modifiedAt` | ISO 8601 datetime of the last write (with milliseconds, used for the panel's conflict check) |

The cms fills these hidden fields on every write of pages, files (`{filename}.json`) and users (`SystemFields`), `site.json` only gets `createdAt` and `modifiedAt`; `uid` and `createdAt` can't be changed. Content written by hand gets them with the next save (or `bin/blueprint content:uids`). Markdown pages (`about.md`, `page.md`) never store them: `createdAt` and `modifiedAt` are the file's modification time, a `uid` or `createdAt` written into the front matter by hand is kept and used.

## Files

All other files in a page folder are the page's files (except `.md`/`.php`, which are subpages, and hidden files). Their data lives next to them in `{filename}.json`:

```
content/01_blog/
  page.md
  cover.jpg
  cover.jpg.json      {"uid": "k2c9…", "template": "image", "alt": "…"}
```

Files are served under the page url: `/blog/cover.jpg`, but **only if they are used**: their filename appears in the data of their page (e.g. `![hello](hello.jpg)` in a textarea), or they are referenced from any page or the site (`file://uid` from a picker, or `blog/hello.jpg`). Unused files are only visible for logged in users (the panel). They are sorted by `sort` in their data (set by dragging them in the panel, `$page->sortFiles([...])`), then by filename. `template` chooses the [file blueprint](/reference/blueprints/files).

## content/site.json

```json
{"name": "My website"}
```

## content/users.json

```json
[
  {"email": "admin", "uid": "19qiajkmcaw3x8bp", "role": "admin", "password": "$2y$12$…"}
]
```

`password` is a `password_hash()`, set it with `bin/blueprint user:password admin`. `passkeys` holds the user's passkeys (added in the panel): `[{id, name, publicKey, algorithm, signCount, createdAt, lastUsedAt}]`, the public key base64url encoded (SPKI); delete an entry to remove a passkey.

## content/redirects.json

```json
{
  "301": {
    "from-foo": "to-bar",
    "old": "https://example.com/new"
  }
}
```
