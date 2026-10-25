---
title: Panel
---
The panel is the admin interface at `/panel`: a Vue app with a sidebar (site, users, pages) and a main view that shows the [blueprint](../05_blueprints/page.md) of the selected model as tabs, columns and native form fields.

The panel is independent of the website: pages are rendered without it, and with `"panel" => false` in the [config](../04_configuration/page.md), `/panel` is an ordinary url again.

## Setup

Build the panel once (Node.js required), the result is written to `public/panel/`:

```sh
composer panel:build
```

Create a user (there is no sign up):

```sh
bin/blueprint user:create admin@example.com admin   # asks for the password
bin/blueprint user:password admin@example.com
bin/blueprint user:list
bin/blueprint content:uids                          # adds missing uids
bin/blueprint media:clear                           # deletes optimized images
```

Then log in at http://localhost:8000/panel.

## What it does

- edit the site, pages and users with the fields of their blueprints (⌘/Ctrl + S saves)
- validation by the blueprint, errors are shown below the fields
- a nested page tree in the sidebar (expandable, listed pages sortable by drag & drop)
- create pages (as drafts), change their status (draft, unlisted, listed), slug and type, sort and delete them
- `pagelist` fields list subpages (sortable), `pagepicker`/`userpicker` are native selects
- files: upload, sort by drag & drop, edit their data, rename, delete; `filelist` and `filepicker` (sortable selection)
- drag a file from a file list or from your computer into a textarea: inserts `![name](file.jpg)` (images) or `[name](file.pdf)`; only used files are public, file lists mark unused ones
- block and layout editors: add, hide, duplicate, sort (drag the handle) and remove blocks and rows
- create users, change email, role and password

## Development

```sh
composer serve      # php on :8000
composer panel:dev  # vite on :5173, open http://localhost:5173/panel/
```

Source: [`panel/src`](../../../panel/src) – [`App.vue`](../../../panel/src/App.vue) (layout), [`views/`](../../../panel/src/views) (site, page, users), [`FieldInput.vue`](../../../panel/src/components/FieldInput.vue) (one native input per field type), [`api.js`](../../../panel/src/api.js) (REST client). The backend is [`src/Panel.php`](../../../src/Panel.php), see the [API reference](../../02_reference/07_panel-api/page.md).
