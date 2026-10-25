---
title: Blog
---
## Content

```
content/
  01_blog/
    page.md                    template: blog
    01_hello-world/
      page.md                  template: article
```

```md
---
title: Hello world
template: article
date: 2026-10-25
---
The first article.
```

## Blueprints

```yaml
# site/blueprints/pages/blog.yml
title: Blog

fields:
  intro:
    type: textarea
  articles:
    type: pagelist
    template: article
    layout: table

tabs:
  content:
    fields: [intro, articles]
```

```yaml
# site/blueprints/pages/article.yml
title: Article
icon: 📝

fields:
  title:
    type: text
    required: true
  date:
    type: date
    default: today
  content:
    type: textarea
    size: large
```

## Templates

```php
<?php // site/templates/blog.php ?>
<?php partial("head") ?>
<h1><?= $page->title() ?></h1>
<?= $page->intro() ?>

<?php foreach ($page->children() as $article): ?>
  <a href="<?= esc($article->url()) ?>"><?= $article->title() ?></a>
<?php endforeach ?>
<?php partial("footer") ?>
```

```php
<?php // site/templates/article.php ?>
<?php partial("head") ?>
<h1><?= $page->title() ?></h1>
<time><?= $page->date()->format("d.m.Y") ?></time>
<?= $page->content() ?>
<?php partial("footer") ?>
```
