<?php

use Blueprint\User;

return [
    "command" => "user:list",
    "description" => "lists all users",
    "run" => function () {
        foreach (User::all() as $user) {
            echo "{$user->uid()}  {$user->email()}  {$user->role()}", PHP_EOL;
        }
    },
];
