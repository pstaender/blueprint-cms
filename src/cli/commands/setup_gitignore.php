<?php

use Blueprint\Setup;

return [
    "command" => "setup:gitignore",
    "description" => "adds the lines that Blueprint CMS recommends to the .gitignore",
    "run" => function () {
        Setup::inTerminal()->gitignore();
    },
];
