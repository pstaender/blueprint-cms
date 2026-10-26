---
title: Panel API
sort: 7
---
A JSON REST API under `/panel/api`, implemented in `src/Panel.php`.

- All endpoints except the login need a session (cookie, `HttpOnly`, `SameSite=Lax`).
- Changes (`POST`, `PATCH`, `DELETE`) need the `X-CSRF-Token` header with the token from the login/session response.
- Page ids are slug paths with `/` (unlike Kirby's `+`): `/pages/blog/newest_entry/files`. The slugs `children` and `files` are reserved (422 when creating or renaming a page), they would be ambiguous. The panel uses the same urls: `/panel/pages/blog/newest_entry`.
- `PATCH` with `{data: {...}}` only changes the given fields (`null` or `""` removes one), the others stay. The panel only sends the changed fields, so editors don't overwrite each other's changes in other fields.
- Conflict check: add `modifiedAt` (the model's `modifiedAt` when you loaded it) and `original` (the loaded values of the fields in `data`) to a `PATCH` of a page, file, the site or a user. If the model was saved in the meantime and one of your fields has changed since you loaded it, the response is `409` with `{"error": "…", "conflicts": ["title"]}` and nothing is saved. Without `modifiedAt` the save overwrites (the panel's "Overwrite with mine").
- Errors: `{"error": "message"}` with status 400, 401, 403, 404, 405 or 422 (validation, with `"errors": {"field": ["message"]}`).

| Method | Path | Body | |
|---|---|---|---|
| `POST` | `/session` | `{email, password}` | log in, returns `{user, csrf, site}` |
| `GET` | `/session` | | the logged in user |
| `DELETE` | `/session` | | log out |
| `GET` | `/site` | | `{title, blueprint, data, children}` |
| `PATCH` | `/site` | `{data: {...}}` | save fields |
| `POST` | `/site/children` | `{title, slug?, type?}` | create a draft at the root |
| `GET` | `/pages?search=` | | all pages (flat), e.g. for pickers |
| `GET` | `/pages/{id}` | | `{id, uid, ref, title, status, num, url, type, blueprint, data, breadcrumb, children, files, …}` |
| `PATCH` | `/pages/{id}` | `{data?, contentFormat?, slug?, type?, status?, num?}` | save fields, change the storage format (`json`, `md`, `json+md`), rename the slug, change the type, status (`draft`, `unlisted`, `listed`) or position |
| `DELETE` | `/pages/{id}` | | delete the page and its subpages |
| `GET` | `/pages/{id}/children` | | subpages (summaries), e.g. for the page tree |
| `POST` | `/pages/{id}/children` | `{title, slug?, type?}` | create a draft |
| `GET` | `/pages/{id}/files` | | files of the page |
| `POST` | `/pages/{id}/files?filename=…&template=…` | the file (raw body) | upload a file |
| `PATCH` | `/pages/{id}/files` | `{sort: [filenames]}` | sort the files, returns the sorted files |
| `GET` | `/pages/{id}/files/{filename}` | | `{filename, uid, ref, url, type, mime, size, modified, dimensions, template, blueprint, data, …}` |
| `PATCH` | `/pages/{id}/files/{filename}` | `{data?, name?}` | save fields, rename (without extension) |
| `PUT` | `/pages/{id}/files/{filename}?filename=…` | the new file (raw body) | replace the file, the extension of `filename` must match; filename, uid and data stay |
| `DELETE` | `/pages/{id}/files/{filename}` | | delete the file and its data |
| `GET` | `/blueprints/pages` | | page types: `[{type, title, icon}]`, `page` first (`default` only if both blueprints exist) |
| `GET` | `/roles` | | `[{name, title}]`, see [roles](/reference/blueprints/users#roles) |
| `GET` | `/users` | | all users (without passwords), non-admins only get themselves |
| `POST` | `/users` | `{email, password, role?}` | create a user (admins only) |
| `GET` | `/users/{uid}` | | user data, `role`, `blueprint`; non-admins only themselves (403 for other users, also for `PATCH`) |
| `PATCH` | `/users/{uid}` | `{data?, email?, role?, password?, currentPassword?}` | a new `password` needs the logged in user's `currentPassword` (422 if wrong), except for admins; changing the `role` needs an admin (403) |
| `DELETE` | `/users/{uid}` | | admins only, not yourself |

`ref` is the value pickers store: `page://uid`, `file://uid`, `user://uid` (the path or filename for models without uid).

Only fields of the blueprint that store a value are saved, other data in the content file is kept. Empty values remove the field from the file.

```sh
curl -c cookies -H "Content-Type: application/json" \
  -d '{"email": "admin@example.com", "password": "…"}' http://localhost:8000/panel/api/session
```
