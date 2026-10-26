<?php

use Blueprint\Cli;

beforeEach(fn() => createApp(WEBSITE_PROJECT));

it("registers the core commands and the commands in site/cli", function () {
    expect(array_keys(Cli::commands()))
        ->toContain("user:create", "user:password", "user:list", "content:uids", "media:clear", "greet");
});

it("runs a command with its arguments and returns its exit code", function () {
    ob_start();
    $code = Cli::run(["bin/blueprint", "greet", "Ada"]);

    expect(ob_get_clean())->toBe("Hello Ada")->and($code)->toBe(3);
});

it("prints the usage for unknown commands", function () {
    ob_start();
    $code = Cli::run(["bin/blueprint", "nope"]);

    expect(ob_get_clean())->toContain("bin/blueprint greet <name>", "greets someone")->and($code)->toBe(1);
});
