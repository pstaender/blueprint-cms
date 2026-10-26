<?php

use Blueprint\App;
use StaticSiteGenerator\StaticSiteGenerator;

return [
    "command" => "static:build",
    "usage" => "[folder] [--clear-existing-target-folder] [--base-path=/subfolder]",
    "description" => "renders the listed and unlisted pages into a static website (default folder: static), --base-path prefixes the site's root-relative links when it's hosted in a subfolder",
    "run" => function (array $args) {
        $flags = array_values(array_filter($args, fn(string $arg) => str_starts_with($arg, "--")));
        $paths = array_values(array_diff($args, $flags));
        $basePathFlags = array_filter($flags, fn(string $flag) => str_starts_with($flag, "--base-path="));
        $unknown = array_diff($flags, ["--clear-existing-target-folder", ...$basePathFlags]);

        if ($unknown !== [] || count($paths) > 1 || count($basePathFlags) > 1) {
            throw new InvalidArgumentException("Usage: bin/blueprint static:build [folder] [--clear-existing-target-folder] [--base-path=/subfolder]");
        }

        $target = StaticSiteGenerator::targetDir($paths[0] ?? "static", in_array("--clear-existing-target-folder", $flags, true));
        $basePath = substr(array_values($basePathFlags)[0] ?? "--base-path=", strlen("--base-path="));
        $generator = new StaticSiteGenerator(App::instance(), $target, $basePath);
        $generator->build();

        foreach ($generator->errors as $error) {
            fwrite(STDERR, "Skipped $error" . PHP_EOL);
        }

        echo "Wrote $generator->pages pages, $generator->files files and $generator->redirects redirects to $target", PHP_EOL;

        return $generator->errors === [] ? 0 : 1;
    },
];
