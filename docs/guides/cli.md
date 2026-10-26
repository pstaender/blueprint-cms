---
title: Command line
sort: 8
---
`bin/blueprint` runs commands, without arguments it lists them (own commands under "User defined commands:"). In a website that requires Blueprint as a composer package, it is `vendor/bin/blueprint`:

```sh
bin/blueprint user:create <email> [role]   # creates a user (the first one is an admin), asks for the password
bin/blueprint user:password <email|uid>    # sets a new password
bin/blueprint user:list
bin/blueprint content:uids                 # adds a uid to all pages, files and users without one
bin/blueprint media:clear                  # deletes the optimized images
bin/blueprint public:build [--force]       # copies index.php, .htaccess and the panel into public/
```

`user:create` and `user:password` read the password from `BLUEPRINT_PASSWORD` if it is set.

`public:build` copies the front controller `index.php`, the `.htaccess` (for Apache) and the built panel of the installed package into the website's `public/`. The panel is always replaced, an existing `index.php` and `.htaccess` are kept (they may be customized) unless `--force` is given. Run it after every update of the package.

## Own commands

Every php file in `site/cli/` is a command. It returns its name, a description, optionally the arguments for the usage and a `run` function, which gets the arguments after the command name:

```php
<?php // site/cli/greet.php

return [
    "command" => "greet",
    "usage" => "<name>",
    "description" => "greets someone",
    "run" => function (array $args) {
        echo "Hello {$args[0]}", PHP_EOL;
    },
];
```

```sh
bin/blueprint greet Ada
```

`run` can return an integer as exit code, otherwise it is `0`; an exception prints its message and exits with `1`. The app is already created, so `site()`, `page()` and all classes can be used. The core commands are in `src/cli/commands/`, [plugins](/guides/plugins) add commands in their `cli/` folder. A site command with the same name replaces a plugin or core command.
