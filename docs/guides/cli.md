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
bin/blueprint public:setup [--force]       # copies index.php, .htaccess and the panel into public/
bin/blueprint setup                        # sets up a website step by step (asks questions)
bin/blueprint setup:gitignore              # adds the recommended lines to the .gitignore
bin/blueprint setup:composer               # lets composer run public:setup after install and update, adds composer serve
bin/blueprint repl                         # php shell with the loaded app
```

`user:create` and `user:password` read the password from `BLUEPRINT_PASSWORD` if it is set.

`public:setup` copies the front controller `index.php`, the `.htaccess` (for Apache) and the built panel of the installed package into the website's `public/`. The panel is always replaced, an existing `index.php` and `.htaccess` are kept (they may be customized) unless `--force` is given. Run it after every update of the package.

`setup` runs `public:setup`, creates a basic site folder (from the package's `site_template/`) and `content/home.md` if they don't exist, the first (admin) user and other users, `setup:gitignore`, `setup:composer` and writes the most important config options, see [Getting started](/guides/getting-started#as-a-composer-package). The setup commands need a terminal, they ask before they change anything. `setup:gitignore` compares the `.gitignore` with the template of the package (comments aside) and adds the missing lines with their comments.

`repl` starts [PsySH](https://psysh.org) with the created app: `$app`, `$site`, the helpers (`site()`, `page("blog")`, `md()`, …) and Blueprint's classes without their namespace (`User::all()`, `Page::find(...)`). `doc`, `show` and `ls` explain functions and objects, ctrl+d exits.

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
