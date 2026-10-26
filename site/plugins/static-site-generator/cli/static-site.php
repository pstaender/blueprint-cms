<?php

use Blueprint\App;
use StaticSiteGenerator\StaticSiteGenerator;

return [
    "command" => "static:build",
    "usage" => "[folder] [--clear-existing-target-folder]",
    "description" => "renders the listed and unlisted pages into a static website (default folder: static)",
    "run" => function (array $args) {
        $flags = array_values(array_filter($args, fn(string $arg) => str_starts_with($arg, "--")));
        $paths = array_values(array_diff($args, $flags));
        $unknown = array_diff($flags, ["--clear-existing-target-folder"]);

        if ($unknown !== [] || count($paths) > 1) {
            throw new InvalidArgumentException("Usage: bin/blueprint static:build [folder] [--clear-existing-target-folder]");
        }

        $target = StaticSiteGenerator::targetDir($paths[0] ?? "static", in_array("--clear-existing-target-folder", $flags, true));
        $generator = new StaticSiteGenerator(App::instance(), $target);
        $generator->build();

        foreach ($generator->errors as $error) {
            fwrite(STDERR, "Skipped $error" . PHP_EOL);
        }

        echo "Wrote $generator->pages pages, $generator->files files and $generator->redirects redirects to $target", PHP_EOL;

        return $generator->errors === [] ? 0 : 1;
    },
];
