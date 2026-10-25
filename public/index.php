<?php

declare(strict_types=1);

use Blueprint\App;
use Blueprint\Config;

// Let PHP's built-in dev server serve existing static files (assets) directly.
if (PHP_SAPI === "cli-server") {
    $file = __DIR__ . parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
    if (is_file($file)) {
        return false;
    }
}

define("PROJECT_DIR", dirname(__DIR__));

require PROJECT_DIR . "/vendor/autoload.php";

App::create(Config::load(PROJECT_DIR . "/config"))->run();
