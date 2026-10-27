<?php

use Blueprint\App;
use Psy\Configuration;
use Psy\Shell;

return [
    "command" => "repl",
    "description" => "php shell (PsySH) with the loaded app: \$app, \$site, helpers and classes without namespace",
    "run" => function () {
        // User::all() instead of Blueprint\User::all()
        spl_autoload_register(function (string $class) {
            if (!str_contains($class, "\\") && class_exists("Blueprint\\$class")) {
                class_alias("Blueprint\\$class", $class);
            }
        });
        $shell = new Shell(new Configuration([
            "startupMessage" => "Blueprint CMS – \$app, \$site, site(), page('path'), User::all(), … (exit with ctrl+d)",
        ]));
        $shell->setScopeVariables(["app" => App::instance(), "site" => site()]);

        return $shell->run();
    },
];
