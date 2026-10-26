---
title: Validating data
sort: 4
---
```yaml
# site/blueprints/pages/contact.yml
fields:
  name:
    type: text
    required: true
  email:
    type: email
    required: true
  topic:
    type: select
    options: {sales: Sales, support: Support}
  message:
    type: textarea
    minlength: 10
```

```php
use Blueprint\Blueprint;

Blueprint::load("pages/contact")->validate([
    "name" => "Ada",
    "topic" => "jobs",
    "message" => "Hello!",
]);
// [
//     "email" => ["Email is required"],
//     "topic" => ["Topic is not a valid select value"],
//     "message" => ["Message must be at least 10 characters long"],
// ]
```

## Check all pages

A script that validates the content of all pages against their blueprints:

```php
<?php // bin/check-content.php
use Blueprint\App;
use Blueprint\Config;
use Blueprint\Page;

define("PROJECT_DIR", dirname(__DIR__));
require PROJECT_DIR . "/vendor/autoload.php";

App::create(Config::load(PROJECT_DIR . "/config"));

$check = function (iterable $pages) use (&$check) {
    foreach ($pages as $page) {
        foreach ($page->blueprint()->validate($page->data()) as $field => $messages) {
            echo $page->id(), " › $field: ", implode(", ", $messages), PHP_EOL;
        }
        $check($page->allChildren());
    }
};

$check(site()->allChildren());
```

See [Validation](/reference/fields/validation).
