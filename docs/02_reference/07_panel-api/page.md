---
title: Panel API
---
A JSON REST API under `/panel/api`, implemented in [`src/Panel.php`](../../../src/Panel.php).

- All endpoints except the login need a session (cookie, `HttpOnly`, `SameSite=Lax`).
- Changes (`POST`, `PATCH`, `DELETE`) need the `X-CSRF-Token` header with the token from the login/session response.
- Page ids in urls use `+` instead of `/`: `blog/newest_entry` → `blog+newest_entry`.
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
| `PATCH` | `/pages/{id}` | `{data?, slug?, type?, status?, num?}` | save fields, rename the slug, change the type, status (`draft`, `unlisted`, `listed`) or position |
| `DELETE` | `/pages/{id}` | | delete the page and its subpages |
| `GET` | `/pages/{id}/children` | | subpages (summaries), e.g. for the page tree |
| `POST` | `/pages/{id}/children` | `{title, slug?, type?}` | create a draft |
| `GET` | `/pages/{id}/files` | | files of the page |
| `POST` | `/pages/{id}/files?filename=…&template=…` | the file (raw body) | upload a file |
| `GET` | `/pages/{id}/files/{filename}` | | `{filename, uid, ref, url, type, mime, size, dimensions, template, blueprint, data, …}` |
| `PATCH` | `/pages/{id}/files/{filename}` | `{data?, name?}` | save fields, rename (without extension) |
| `DELETE` | `/pages/{id}/files/{filename}` | | delete the file and its data |
| `GET` | `/blueprints/pages` | | page types: `[{type, title, icon}]` |
| `GET` | `/users` | | all users (without passwords) |
| `POST` | `/users` | `{email, password, role?}` | create a user |
| `GET` | `/users/{uid}` | | user data, `role`, `blueprint` |
| `PATCH` | `/users/{uid}` | `{data?, email?, role?, password?}` | |
| `DELETE` | `/users/{uid}` | | (not yourself) |

`ref` is the value pickers store: `page://uid`, `file://uid`, `user://uid` (the path or filename for models without uid).

Only fields of the blueprint that store a value are saved, other data in the content file is kept. Empty values remove the field from the file.

```sh
curl -c cookies -H "Content-Type: application/json" \
  -d '{"email": "admin@example.com", "password": "…"}' http://localhost:8000/panel/api/session
```
