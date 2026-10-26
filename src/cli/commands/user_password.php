<?php

use Blueprint\Cli;
use Blueprint\User;

return [
    "command" => "user:password",
    "usage" => "<email|uid>",
    "description" => "sets a new password",
    "run" => function (array $args) {
        $user = User::find($args[0] ?? "") ?? throw new InvalidArgumentException("User not found");
        $user->changePassword(Cli::askPassword());
        echo "Password of {$user->email()} changed", PHP_EOL;
    },
];
