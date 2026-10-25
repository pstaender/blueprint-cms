---
title: User blueprints
---
`site/blueprints/users/{role}.yml` – chosen by the user's `role` in `content/users.json` (`default` if not set), falls back to `users/default`.

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
