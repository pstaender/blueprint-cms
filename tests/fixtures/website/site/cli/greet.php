<?php

return [
    "command" => "greet",
    "usage" => "<name>",
    "description" => "greets someone",
    "run" => function (array $args) {
        echo "Hello {$args[0]}";
        return 3;
    },
];
