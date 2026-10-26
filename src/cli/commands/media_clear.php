<?php

use Blueprint\App;
use Blueprint\ImageOptimizer;

return [
    "command" => "media:clear",
    "description" => "deletes the optimized images (they are created again when needed)",
    "run" => function () {
        $dir = App::instance()->publicDir() . ImageOptimizer::URL_PATH;
        $files = glob("$dir/*/*.avif") ?: [];
        array_map("unlink", $files);
        array_map("rmdir", glob("$dir/*", GLOB_ONLYDIR) ?: []);
        echo "Deleted " . count($files) . " optimized images", PHP_EOL;
    },
];
