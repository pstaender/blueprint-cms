---
title: User blueprints
---
`site/blueprints/users/{role}.yml` – chosen by the user's `role` in `content/users.json` (`user` if not set). Lookup:

1. `users/{role}`
2. `users/user`
3. `users/default` (site, then core)

```yaml
# site/blueprints/users/editor.yml
title: Editor
fields:
  bio:
    type: textarea
```

## Roles

The available roles are `user`, `admin` (core blueprint [`src/blueprints/users/admin.yml`](../../../../src/blueprints/users/admin.yml), extends `users/default`) and every user blueprint (`site/blueprints/users/{role}.yml`, except `default`). With `users/editor.yml`, the roles are `user`, `admin` and `editor`. Admins (`$user->isAdmin()`) change passwords in the panel without confirming them with their own password, everybody else needs it. Users can only get one of them (the panel shows a select, or just the role if `user` is the only one); a user keeps their role if its blueprint is removed.

```php
Blueprint\User::roles();  // ["user", "admin", "editor"]
```

Options: `changeEmail`, `changeName`, `changePassword`, `changeRole`, `create`, `delete`, `update`.

Core fallback: [`src/blueprints/users/default.yml`](../../../../src/blueprints/users/default.yml).

```php
user("editor@example.com")->role();       // "editor"
user("editor@example.com")->blueprint();
```
