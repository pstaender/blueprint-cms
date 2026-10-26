<?php

return [
    "command" => "my:exampleCommand",
    "description" => "The description of my example command",
    "run" => function ($args) {
        echo("This is an example task");
        return 0;
    }
];
