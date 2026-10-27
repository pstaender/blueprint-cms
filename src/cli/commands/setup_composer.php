<?php

use Blueprint\Setup;

return [
    "command" => "setup:composer",
    "description" => "lets composer run public:setup after install and update",
    "run" => function () {
        Setup::inTerminal()->composer();
    },
];
