---
title: Site blueprint
---
`site/blueprints/site.yml`, the data is stored in `content/site.json`.

```yaml
title: Website

tabs:
  pages:
    fields:
      pages:
        type: pagelist
  settings:
    fields:
      name:
        type: text
        required: true
```

Options: `changeTitle`, `preview`, `update`.

Core fallback: [`src/blueprints/site.yml`](../../../../src/blueprints/site.yml).

```php
site()->blueprint();
```
