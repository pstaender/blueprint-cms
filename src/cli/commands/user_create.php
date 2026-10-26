<?php

use Blueprint\Cli;
use Blueprint\User;

return [
    "command" => "user:create",
    "usage" => "<email> [role]",
    "description" => "creates a user (the first one is an admin), asks for the password",
    "run" => function (array $args) {
        $user = User::create($args[0] ?? "", Cli::askPassword(), $args[1] ?? null);
        echo "Created {$user->email()} ({$user->uid()})", PHP_EOL;
    },
];
