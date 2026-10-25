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
| `POST` | `/site/children` | `{title, slug?, template?}` | create a draft at the root |
| `GET` | `/pages?search=` | | all pages (flat), e.g. for pickers |
| `GET` | `/pages/{id}` | | `{id, title, status, num, url, template, blueprint, data, breadcrumb, children, …}` |
| `PATCH` | `/pages/{id}` | `{data?, status?, num?}` | save fields, change status (`draft`, `unlisted`, `listed`) or position |
| `DELETE` | `/pages/{id}` | | delete the page and its subpages |
| `POST` | `/pages/{id}/children` | `{title, slug?, template?}` | create a draft |
| `GET` | `/blueprints/pages` | | page templates: `[{template, title, icon}]` |
| `GET` | `/users` | | all users (without passwords) |
| `POST` | `/users` | `{email, password, role?}` | create a user |
| `GET` | `/users/{uid}` | | user data, `role`, `blueprint` |
| `PATCH` | `/users/{uid}` | `{data?, email?, role?, password?}` | |
| `DELETE` | `/users/{uid}` | | (not yourself) |

Only fields of the blueprint that store a value are saved, other data in the content file is kept. Empty values remove the field from the file.

```sh
curl -c cookies -H "Content-Type: application/json" \
  -d '{"email": "admin@example.com", "password": "…"}' http://localhost:8000/panel/api/session
```
