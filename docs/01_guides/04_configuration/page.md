---
title: Configuration
---
Config files return arrays (values and lambdas):

```php
<?php // config/config.php
return [
    "debug" => true,
];
```

They are merged in this order (later wins):

1. defaults ([`Config::DEFAULTS`](../../../src/Config.php))
2. `site/config/config.php`, `site/config/config.{hostname}.php`
3. `config/config.php`, `config/config.{hostname}.php`

`config.{hostname}.php` is only loaded on that host, e.g. `config.example.com.php`. Use it for environment specific settings.

```php
App::instance()->config->get("debug");
```

All options: [Config reference](../../02_reference/05_config/page.md).
