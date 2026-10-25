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

Options: `changeEmail`, `changeName`, `changePassword`, `changeRole`, `create`, `delete`, `update`.

Core fallback: [`src/blueprints/users/default.yml`](../../../../src/blueprints/users/default.yml).

```php
user("editor@example.com")->role();       // "editor"
user("editor@example.com")->blueprint();
```
