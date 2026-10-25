<?php

declare(strict_types=1);

use Blueprint\App;
use Blueprint\Page;
use Blueprint\Redirect;
use Blueprint\Site;
use Blueprint\User;
use Michelf\MarkdownExtra;

/**
 * page() returns the current page, page("blog") finds a page by its path.
 */
function page(?string $path = null): ?Page
{
    return $path === null ? Page::current() : Page::find($path);
}

function site(): Site
{
    return App::instance()->site();
}

/**
 * user() returns the logged in user, user("admin") finds a user by email/username or uid.
 */
function user(?string $emailOrUid = null): ?User
{
    return $emailOrUid === null ? User::current() : User::find($emailOrUid);
}

function md(?string $markdown): string
{
    return $markdown === null || $markdown === "" ? "" : MarkdownExtra::defaultTransform($markdown);
}

function esc(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
}

/**
 * Stops rendering and redirects, e.g. redirect("/test") or redirect("/tmp_redirect", 307).
 */
function redirect(string $url, int $status = 302): never
{
    throw new Redirect($url, $status);
}

/**
 * Renders site/templates/partials/{name}.php, e.g. partial("head").
 */
function partial(string $name, array $variables = [], bool $return = false): ?string
{
    $html = App::instance()->renderPartial($name, $variables);

    if ($return) {
        return $html;
    }

    echo $html;

    return null;
}

/**
 * Appends the file's modification time for cache busting, e.g. /assets/css/layout.css?1700000000.
 */
function static_file_url(string $path): string
{
    $path = "/" . ltrim($path, "/");
    $file = App::instance()->publicDir() . $path;

    return is_file($file) ? "$path?" . filemtime($file) : $path;
}

if (!function_exists("dd")) {
    /**
     * Dumps the given values and ends the script.
     */
    function dd(mixed ...$values): never
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        foreach ($values as $value) {
            ob_start();
            var_dump($value);
            $dump = (string) ob_get_clean();

            echo PHP_SAPI === "cli" ? $dump : "<pre>" . esc($dump) . "</pre>";
        }

        exit(1);
    }
}
