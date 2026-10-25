---
title: Reusable fields
---
Define the fields once in `site/blueprints/custom-fields/`:

```yaml
# site/blueprints/custom-fields/seo.yml
type: group
fields:
  seoTitle:
    label: SEO title
    type: text
    maxlength: 60
  seoDescription:
    label: SEO description
    type: textarea
```

Use them in any blueprint, the group is replaced by its fields:

```yaml
# site/blueprints/pages/article.yml
fields:
  title:
    type: text
  seo: custom-fields/seo

tabs:
  content:
    fields: [title]
  seo:
    label: SEO
    fields: [seo]
```

Single fields can be extended and adjusted, too:

```yaml
# site/blueprints/custom-fields/date.yml
type: date
display: DD.MM.YYYY
```

```yaml
fields:
  publishedAt:
    extends: custom-fields/date
    label: Published at
```

Whole tabs work the same way:

```yaml
# site/blueprints/tabs/seo.yml
label: SEO
fields:
  seoTitle: text
```

```yaml
tabs:
  seo: tabs/seo
```

Output in a partial:

```php
<?php // site/templates/partials/head.php ?>
<title><?= $page->seoTitle()->or($page->title()) ?></title>
<meta name="description" content="<?= $page->seoDescription()->stripTags() ?>">
```
