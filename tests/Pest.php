<?php

use Blueprint\App;
use Blueprint\Config;
use Blueprint\User;
use Psr\Http\Message\ResponseInterface;

const PROJECT_ROOT = __DIR__ . "/..";
const FIXTURE_PROJECT = __DIR__ . "/fixtures/project";

function createApp(string $projectDir = PROJECT_ROOT, array $config = []): App
{
    User::setCurrent(null);
    $values = Config::readFolder("$projectDir/config", hostname: "");

    return App::create(new Config(array_replace($values, $config), $projectDir));
}

function body(ResponseInterface $response): string
{
    return (string) $response->getBody();
}
