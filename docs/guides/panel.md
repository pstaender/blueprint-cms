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
- `pagelist` fields list subpages (sortable); every page and the site have one by default (`subpages`, see [Page blueprints](/reference/blueprints/pages#subpages)); `pagepicker`/`userpicker` are native selects
- files: upload, sort by drag & drop, edit their data, rename, delete; right click a file in a list (or its ⋯ button) for open, rename, change position, replace and delete; `filelist` and `filepicker` (sortable selection)
- drag a file from a file list or from your computer into a textarea: inserts `![name](file.jpg)` (images) or `[name](file.pdf)`; only used files are public, file lists mark unused ones
- block and layout editors: add, hide, duplicate, sort (drag the handle) and remove blocks and rows
- admins create, edit and delete users and change roles; everybody else only sees their own account (email, fields, password confirmed with the current password)

## Icons

A blueprint's `icon` (page types, tabs, …) can be:

```yaml
icon: home                                  # the name of a panel icon
icon: 👍                                    # any text, usually an emoji
icon: data:image/svg+xml;base64,PHN2Zy…     # an inline image (data URI)
```

Inline images must be `data:image/…` URIs (other data URIs are ignored). They are used as a mask, so they are drawn in the text color of the panel (light and dark mode); transparent parts stay transparent.

Panel icons (the names follow [Kirby's icon set](https://getkirby.com/docs/reference/panel/icons), without its brand logos): `accessibility`, `account`, `add`, `ai`, `alert`, `anchor`, `angle-down`, `angle-dropdown`, `angle-left`, `angle-right`, `angle-up`, `archive`, `asterisk`, `attachment`, `audio`, `badge`, `bars`, `bell`, `blocks`, `bold`, `bolt`, `book`, `bookmark`, `box`, `brush`, `bug`, `calendar`, `cancel`, `cancel-small`, `car`, `cart`, `chart`, `chat`, `check`, `checklist`, `chevron`, `circle`, `circle-filled`, `circle-half`, `circle-nested`, `clear`, `clock`, `close`, `cloud`, `code`, `cog`, `collapse`, `collapse-horizontal`, `copy`, `credit-card`, `crop`, `dashboard`, `deselect-all`, `discount`, `display`, `divider`, `document`, `dots`, `down`, `download`, `draft`, `edit`, `edit-line`, `email`, `expand`, `expand-horizontal`, `eye`, `file`, `file-audio`, `file-code`, `file-document`, `file-image`, `file-spreadsheet`, `file-text`, `file-video`, `file-word`, `file-zip`, `filter`, `flag`, `folder`, `folder-structure`, `food`, `git-branch`, `globe`, `grid`, `grid-bottom`, `grid-bottom-left`, `grid-bottom-right`, `grid-full`, `grid-left`, `grid-right`, `grid-top`, `grid-top-left`, `grid-top-right`, `grip`, `h1`, `h2`, `h3`, `h4`, `h5`, `h6`, `headline`, `heart`, `heart-filled`, `hidden`, `home`, `image`, `images`, `import`, `info`, `info-card`, `italic`, `key`, `lab`, `layers`, `layout-bottom`, `layout-columns`, `layout-left`, `layout-right`, `layout-top`, `link`, `list-bullet`, `list-numbers`, `live`, `loader`, `lock`, `logout`, `map`, `markdown`, `megaphone`, `menu`, `merge`, `mobile`, `money`, `moon`, `more`, `open`, `order-alpha-asc`, `order-alpha-desc`, `order-num-asc`, `order-num-desc`, `page`, `palette`, `paragraph`, `parent`, `pen`, `phone`, `pin`, `pipette`, `plane`, `play`, `plus`, `preview`, `print`, `protected`, `qr-code`, `question`, `quote`, `refresh`, `remove`, `replace`, `rocket`, `rss`, `scroll-to-bottom`, `search`, `select-all`, `server`, `settings`, `share`, `shield`, `shuffle`, `shut-down`, `site`, `sitemap`, `smile`, `sort`, `sparkling`, `split`, `star`, `star-filled`, `status-draft`, `status-listed`, `status-unlisted`, `store`, `strikethrough`, `subscript`, `sun`, `superscript`, `table`, `tablet`, `tag`, `template`, `terminal`, `text`, `text-bottom`, `text-center`, `text-justify`, `text-left`, `text-middle`, `text-right`, `text-top`, `ticket`, `title`, `toggle-off`, `toggle-on`, `translate`, `trash`, `underline`, `undo`, `unlock`, `up`, `upload`, `url`, `user`, `users`, `video`, `wallet`, `wand`, `wheelchair`, `window`.

The icons are simple 24×24 line paths written by hand for Blueprint CMS, in the style of [Feather](https://feathericons.com) and [Lucide](https://lucide.dev) but not copied from them (nor from Kirby), so no third party license applies.

## Development

```sh
composer serve      # php on :8000
composer panel:dev  # vite on :5173, open http://localhost:5173/panel/
```

Source: `panel/src` – `App.vue` (layout), `views/` (site, page, users), `FieldInput.vue` (one native input per field type), `api.js` (REST client). The backend is `src/Panel.php`, see the [API reference](/reference/panel-api).
