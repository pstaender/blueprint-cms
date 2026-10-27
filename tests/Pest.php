<?php

use Blueprint\App;
use Blueprint\Config;
use Blueprint\User;
use Psr\Http\Message\ResponseInterface;

const WEBSITE_PROJECT = __DIR__ . "/fixtures/website";
const FIXTURE_PROJECT = __DIR__ . "/fixtures/project";
const BLUEPRINT_PROJECT = __DIR__ . "/fixtures/blueprints";

function createApp(string $projectDir, array $config = []): App
{
    User::setCurrent(null);
    $_SESSION = [];
    $values = Config::readFolder("$projectDir/config", hostname: "");

    return App::create(new Config(array_replace($values, $config), $projectDir));
}

function body(ResponseInterface $response): string
{
    return (string) $response->getBody();
}

/**
 * A copy of a fixture project in the temp dir, for tests that change files.
 */
function copyProject(string $source): string
{
    $target = sys_get_temp_dir() . "/bpcms-test-" . bin2hex(random_bytes(4));
    exec("cp -R " . escapeshellarg($source) . " " . escapeshellarg($target));

    return $target;
}

function removeProject(string $dir): void
{
    if (str_starts_with($dir, sys_get_temp_dir() . "/bpcms-test-")) {
        exec("rm -rf " . escapeshellarg($dir));
    }
}

/**
 * Logs in to the panel, returns the csrf token.
 */
function login(App $app, string $email = "panel@example.com", string $password = "secret-password"): string
{
    $response = $app->request("/panel/api/session", "POST", ["email" => $email, "password" => $password]);

    return json_decode(body($response), true)["csrf"];
}

function api(App $app, string $method, string $path, ?array $json = null, ?string $csrf = null): array
{
    $response = $app->request("/panel/api$path", $method, $json, $csrf === null ? [] : ["X-CSRF-Token" => $csrf]);

    return [$response->getStatusCode(), json_decode(body($response), true)];
}
