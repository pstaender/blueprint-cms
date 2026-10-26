---
title: Panel
sort: 6
---
The panel is the admin interface at `/panel`: a Vue app with a sidebar (site, users, pages) and a main view that shows the [blueprint](/guides/blueprints) of the selected model as tabs, columns and native form fields.

The panel is independent of the website: pages are rendered without it, and with `"panel" => false` in the [config](/guides/configuration), `/panel` is an ordinary url again.

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

See [Command line](/guides/cli) for all commands.

Then log in at http://localhost:8000/panel.

## What it does

- edit the site, pages and users with the fields of their blueprints (⌘/Ctrl + S saves). Saving only sends the changed fields; if someone else has changed one of them in the meantime, the panel asks whether to load their version or overwrite it with yours
- validation by the blueprint, errors are shown below the fields
- a nested page tree in the sidebar: listed pages first (sortable by drag & drop), then the groups "Unlisted" and "Drafts"; expandable. Pages show the `icon` of their [blueprint](/reference/blueprints/pages#icon), the home page a house unless its blueprint has one
- create pages (as drafts), change their status (draft, unlisted, listed), sort, move and delete them. Right click a page in the tree (or its ⋯ button) to move it or change its status; the page settings also have its parent. Every page can be a draft, except the home page
- page lists (subpages, `pagelist`): select pages with the checkboxes (or all of them) to change their status, move or delete them together
- page settings: slug, type and storage format (`page.json`, `page.md` or `page.json` + `page.content.md`)
- `pagelist` fields list subpages (sortable), `pagepicker`/`userpicker` are native selects
- files: upload, sort by drag & drop, edit their data, rename, delete; right click a file in a list (or its ⋯ button) for open, rename, change position, replace and delete; `filelist` and `filepicker` (sortable selection)
- drag a file from a file list or from your computer into a textarea: inserts `![name](file.jpg)` (images) or `[name](file.pdf)`; only used files are public, file lists mark unused ones
- block and layout editors: add, hide, duplicate, sort (drag the handle) and remove blocks and rows
- admins create, edit and delete users and change roles; everybody else only sees their own account (email, fields, password confirmed with the current password)

## Icons

A blueprint's `icon` is either the name of a panel icon or any text, usually an emoji (`icon: 📝`). Panel icons: `site`, `home`, `page`, `file`, `image`, `blocks`, `user`, `users`, `settings`, `edit`, `upload`, `open`, `copy`, `eye`, `trash`, `check`, … (all in `panel/src/icons.js`).

The icons are simple 24×24 line paths written by hand for Blueprint CMS, in the style of [Feather](https://feathericons.com) and [Lucide](https://lucide.dev) but not copied from them, so no third party license applies.

## Development

```sh
composer serve      # php on :8000
composer panel:dev  # vite on :5173, open http://localhost:5173/panel/
```

Source: `panel/src` – `App.vue` (layout), `views/` (site, page, users), `FieldInput.vue` (one native input per field type), `api.js` (REST client). The backend is `src/Panel.php`, see the [API reference](/reference/panel-api).
