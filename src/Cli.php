<?php

declare(strict_types=1);

namespace Blueprint;

/**
 * Commands of bin/blueprint: php files in src/cli/commands/ and site/cli/ that return
 * ["command" => "name", "description" => "…", "usage" => "<args>" (optional), "run" => function (array $args)],
 * run can return an int as exit code (default 0).
 * Site commands override core commands with the same name.
 */
class Cli
{
    /** @return array<string, array> command name => definition */
    public static function commands(): array
    {
        $files = [
            ...glob(__DIR__ . "/cli/commands/*.php") ?: [],
            ...glob(App::instance()->siteDir() . "/cli/*.php") ?: [],
        ];
        $commands = [];
        foreach ($files as $file) {
            $command = require $file;
            if (!is_array($command) || !is_string($command["command"] ?? null) || !is_callable($command["run"] ?? null)) {
                throw new \UnexpectedValueException("$file must return an array with a command name and a run function");
            }
            $commands[$command["command"]] = $command;
        }
        ksort($commands);

        return $commands;
    }

    /** Runs the command and returns the exit code. */
    public static function run(array $argv): int
    {
        $commands = self::commands();
        $command = $commands[$argv[1] ?? ""] ?? null;
        if ($command === null) {
            echo self::usage($commands);

            return 1;
        }

        try {
            $status = $command["run"](array_slice($argv, 2));

            return is_int($status) ? $status : 0;
        } catch (\Throwable $error) {
            fwrite(STDERR, $error->getMessage() . PHP_EOL);

            return 1;
        }
    }

    public static function usage(array $commands): string
    {
        $lines = array_map(
            fn ($command) => trim("{$command['command']} " . ($command["usage"] ?? "")),
            $commands,
        );
        $width = max(array_map("strlen", $lines ?: [""]));
        $usage = "Usage:" . PHP_EOL;
        foreach ($commands as $name => $command) {
            $usage .= "  bin/blueprint " . str_pad($lines[$name], $width) . "   " . ($command["description"] ?? "") . PHP_EOL;
        }

        return $usage;
    }

    public static function askPassword(): string
    {
        if (getenv("BLUEPRINT_PASSWORD")) {
            return getenv("BLUEPRINT_PASSWORD");
        }

        echo "Password (min. 8 characters): ";
        shell_exec("stty -echo");
        $password = trim((string) fgets(STDIN));
        shell_exec("stty echo");
        echo PHP_EOL;

        return $password;
    }
}
