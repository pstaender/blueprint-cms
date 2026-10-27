<?php

use Blueprint\App;
use Blueprint\PublicFolder;

return [
    "command" => "public:setup",
    "usage" => "[--force]",
    "description" => "copies index.php, .htaccess and the panel into public/, --force replaces index.php and .htaccess",
    "run" => function (array $args) {
        if (array_diff($args, ["--force"]) !== []) {
            throw new InvalidArgumentException("Usage: bin/blueprint public:setup [--force]");
        }

        foreach ((new PublicFolder())->copyTo(App::instance()->publicDir(), in_array("--force", $args, true)) as $line) {
            echo $line, PHP_EOL;
        }
    },
];
