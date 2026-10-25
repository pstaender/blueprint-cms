---
title: File blueprints
---
`site/blueprints/files/{template}.yml` – define the meta data of files.

```yaml
title: Image
accept:
  mime: image/*
fields:
  alt:
    type: text
  caption:
    type: textarea
```

Options: `changeName`, `changeTemplate`, `create`, `delete`, `read`, `replace`, `sort`, `update`.

> File models are not implemented yet, file blueprints can only be loaded with `Blueprint::load("files/image")`.
