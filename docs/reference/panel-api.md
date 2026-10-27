---
title: Panel API
sort: 7
---
A JSON REST API under `/panel/api`, implemented in `src/Panel.php`.

- All endpoints except the login (also with a passkey) need a session (cookie, `HttpOnly`, `SameSite=Lax`).
- Changes (`POST`, `PATCH`, `DELETE`) need the `X-CSRF-Token` header with the token from the login/session response.
- Page ids are slug paths with `/` (unlike Kirby's `+`): `/pages/blog/newest_entry`. Subresources start with `~`, which slugs can't contain, so a page may be called `files` or `children`: `/pages/blog/~files/cover.jpg`. The panel uses the same urls: `/panel/pages/blog/newest_entry`, `/panel/pages/blog/~files/cover.jpg`.
- `PATCH` with `{data: {...}}` only changes the given fields (`null` or `""` removes one), the others stay. The panel only sends the changed fields, so editors don't overwrite each other's changes in other fields.
- Conflict check: add `modifiedAt` (the model's `modifiedAt` when you loaded it) and `original` (the loaded values of the fields in `data`) to a `PATCH` of a page, file, the site or a user. If the model was saved in the meantime and one of your fields has changed since you loaded it, the response is `409` with `{"error": "…", "conflicts": ["title"]}` and nothing is saved. Without `modifiedAt` the save overwrites (the panel's "Overwrite with mine").
- Errors: `{"error": "message"}` with status 400, 401, 403, 404, 405 or 422 (validation, with `"errors": {"field": ["message"]}`).

| Method | Path | Body | |
|---|---|---|---|
| `POST` | `/session` | `{email, password}` | log in, returns `{user, csrf, site, models}` (`models`: the models in the panel) |
| `GET` | `/session` | | the logged in user |
| `DELETE` | `/session` | | log out |
| `POST` | `/session/~passkey-options` | | options for `navigator.credentials.get()` (binary values base64url encoded), the challenge is valid for 5 minutes |
| `POST` | `/session/~passkey` | `{id, clientDataJSON, authenticatorData, signature, userHandle}` (base64url) | log in with a passkey, like `POST /session` |
| `GET` | `/site` | | `{title, blueprint, data, children}` |
| `PATCH` | `/site` | `{data: {...}}` | save fields |
| `POST` | `/site/~children` | `{title, slug?, type?}` | create a draft at the root |
| `GET` | `/pages?search=` | | all pages (flat), e.g. for pickers |
| `GET` | `/pages/{id}` | | `{id, uid, ref, title, status, num, url, type, blueprint, data, breadcrumb, children, files, …}` |
| `PATCH` | `/pages/{id}` | `{data?, contentFormat?, slug?, type?, parent?, status?, num?}` | save fields, change the storage format (`json`, `md`, `json+md`), rename the slug, change the type, move it (`parent`: a folder page id, `null`/`""` = root; keeps its status, subpages and files), change the status (`draft`, `unlisted`, `listed`) or position. Pages with a php file: 403 unless the user is an admin or in `content.allowPHP.edit`, their type can't be changed (400) |
| `DELETE` | `/pages/{id}` | | delete the page and its subpages (403 if one of them has a php file and the user is neither an admin nor in `content.allowPHP.edit`) |
| `PUT` | `/pages/{id}/~php` | `{code, hash?, force?}` | save the page's php file (only for the roles/users in `content.allowPHP.edit`, otherwise 403); a syntax error → 422 with `syntaxError: {message, line}` unless `force: true`; `hash` (`php.hash` of `GET /pages/{id}`) → 409 if the file has changed in the meantime. `GET /pages/{id}` has `php: {file, canEdit, code, hash}` (`code` only for editors) or `null` |
| `GET` | `/pages/{id}/~children` | | subpages (summaries `{id, uid, ref, title, status, num, url, type, icon, isHome, isPhp, canChange, canHaveChildren, statuses, parent, hasChildren}`, `icon` is the value of the page's `icon` field or the blueprint's, `canChange` is `false` for pages with a php file the user may not change), e.g. for the page tree |
| `POST` | `/pages/{id}/~children` | `{title, slug?, type?}` | create a draft |
| `GET` | `/pages/{id}/~files` | | files of the page |
| `POST` | `/pages/{id}/~files?filename=…&template=…` | the file (raw body) | upload a file |
| `PATCH` | `/pages/{id}/~files` | `{sort: [filenames]}` | sort the files, returns the sorted files |
| `GET` | `/pages/{id}/~files/{filename}` | | `{filename, uid, ref, url, type, mime, size, modified, dimensions, template, blueprint, data, …}` |
| `PATCH` | `/pages/{id}/~files/{filename}` | `{data?, name?}` | save fields, rename (without extension) |
| `PUT` | `/pages/{id}/~files/{filename}?filename=…` | the new file (raw body) | replace the file, the extension of `filename` must match; filename, uid and data stay |
| `DELETE` | `/pages/{id}/~files/{filename}` | | delete the file and its data |
| `GET` | `/blueprints/pages` | | page types: `[{type, title, icon}]`, `page` first (`default` only if both blueprints exist) |
| `GET` | `/roles` | | `[{name, title}]`, see [roles](/reference/blueprints/users#roles) |
| `GET` | `/users` | | all users (without passwords), non-admins only get themselves |
| `POST` | `/users` | `{email, password, role?}` | create a user (admins only) |
| `GET` | `/users/{uid}` | | user data, `role`, `blueprint`; non-admins only themselves (403 for other users, also for `PATCH`) |
| `PATCH` | `/users/{uid}` | `{data?, email?, role?, password?, currentPassword?}` | a new `password` needs the logged in user's `currentPassword` (422 if wrong), except for admins; changing the `role` needs an admin (403) |
| `DELETE` | `/users/{uid}` | | admins only, not yourself |
| `GET` | `/models/{name}?search=&sort=&direction=&trash=&offset=&limit=` | | the records of a [model](/guides/models) with `panel: true` (others → 404): `{name, collection, title, icon, softDelete, files, perPage, searchableFields, blueprint, total, trashCount, records}`; `sort`: `createdAt` (default) or `modifiedAt`, `direction`: `desc` (default) or `asc`, `trash=1` lists the trash, `offset` and `limit` page through them (`limit` defaults to the blueprint's `perPage`, 10) |
| `POST` | `/models/{name}` | `{data}` | create a record (validated by the blueprint) |
| `GET` | `/models/{name}/{uid}` | | `{uid, ref, title, createdAt, modifiedAt, isTrashed, model, blueprint, data, files}` |
| `PATCH` | `/models/{name}/{uid}` | `{data, modifiedAt?, original?}` | save fields, with the conflict check |
| `DELETE` | `/models/{name}/{uid}` | | move it to the trash (`softDelete: true`), otherwise or from the trash delete it permanently |
| `POST` | `/models/{name}/{uid}/~restore` | | restore it from the trash |
| `DELETE` | `/models/{name}/~trash` | | empty the trash: delete all trashed records and their files permanently, returns `{deleted}` (the number) |
| `POST` | `/models/{name}/{uid}/~files?filename=` | the file | upload a file of the record (400 with `files: false` in the blueprint) |
| `GET` | `/models/{name}/{uid}/~files/{filename}` | | the file itself (images, pdf, … inline, others as download, sandboxed) |
| `DELETE` | `/models/{name}/{uid}/~files/{filename}` | | delete a file of the record |
| `POST` | `/users/{uid}/~passkey-options` | | options for `navigator.credentials.create()`, only for your own account |
| `POST` | `/users/{uid}/~passkeys` | `{name, id, publicKey, algorithm, clientDataJSON, authenticatorData}` | add a passkey to your own account: `publicKey` from `response.getPublicKey()` (SPKI), `algorithm` from `getPublicKeyAlgorithm()` (ES256 or RS256) |
| `DELETE` | `/users/{uid}/~passkeys/{id}` | | delete a passkey (your own, admins also those of others) |

Passkeys need user verification (pin, biometrics) and an https origin with the request's host as relying party id (`http` only on `localhost`). Users list their passkeys as `passkeys: [{id, name, createdAt, lastUsedAt}]`.

`ref` is the value pickers store: `page://uid`, `file://uid`, `user://uid` (the path or filename for models without uid).

Only fields of the blueprint that store a value are saved, other data in the content file is kept. Empty values remove the field from the file.

```sh
curl -c cookies -H "Content-Type: application/json" \
  -d '{"email": "admin@example.com", "password": "…"}' http://localhost:8000/panel/api/session
```
