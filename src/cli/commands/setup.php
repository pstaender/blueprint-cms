<?php

use Blueprint\Setup;

return [
    "command" => "setup",
    "description" => "sets up the website step by step: public folder, users, .gitignore, composer.json and config",
    "run" => function () {
        Setup::inTerminal()->run();
    },
];
