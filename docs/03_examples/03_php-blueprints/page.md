---
title: PHP blueprints
---
A blueprint can be a php file returning an array, e.g. to build fields with code or use lambdas.

```php
<?php // site/blueprints/pages/event.php

return [
    "title" => "Event",
    "fields" => [
        "title" => ["type" => "text", "required" => true],
        "category" => [
            "type" => "select",
            "options" => ["concert", "lecture", "workshop"],
        ],
        "startsAt" => [
            "type" => "date",
            "time" => true,
            "default" => fn() => date("Y-m-d H:i"),
        ],
    ],
];
```

`.yml` wins over `.php` if both exist. Lambdas are kept as they are:

```php
$default = Blueprint::load("pages/event")->field("startsAt")->defaultValue();
$default(); // "2026-10-25 12:00"
```
