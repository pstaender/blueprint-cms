<?php

declare(strict_types=1);

use Blueprint\App;
use Blueprint\FieldValue;
use Blueprint\File;
use Blueprint\Page;
use Blueprint\Redirect;
use Blueprint\Site;
use Blueprint\Snippet;
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

/**
 * Markdown to html (inline html allowed). Relative file links like `![](hello.jpg)` point to the
 * files of the given (or current) page.
 */
function md(?string $markdown, ?Page $page = null): string
{
    if ($markdown === null || $markdown === "") {
        return "";
    }

    return File::resolveUrls(MarkdownExtra::defaultTransform($markdown), $page ?? Page::current());
}

/**
 * Field values are already formatted and escaped, they are returned as they are.
 */
function esc(mixed $value): string
{
    if ($value instanceof FieldValue) {
        return $value->toString();
    }

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
 * Returns the output of site/templates/partials/{name}.php or of an absolute php file, e.g. <?= partial("head") ?>,
 * <?= partial(__DIR__ . "/partials/head.php", ["title" => "Hello"]) ?>. Throws if the partial doesn't exist.
 *
 * @param array<string, mixed> $variables available as variables in the partial (besides $page and $site)
 */
function partial(string $name, array $variables = []): string
{
    return App::instance()->renderPartial($name, $variables);
}

/**
 * Renders site/templates/snippets/{name}.php like Kirby, e.g. snippet("header"), snippet(["teaser-" . $page->type(), "teaser"])
 * (the first existing one). Missing snippets render nothing. With `slots: true` the snippet is closed by endsnippet(),
 * see Snippet.
 */
function snippet(string|array $name, array|object $data = [], bool $return = false, bool $slots = false): Snippet|string|null
{
    $data = is_object($data) ? get_object_vars($data) : $data;

    if ($slots) {
        return Snippet::begin($name, $data, $return);
    }

    $html = App::instance()->renderSnippet($name, $data);

    if ($return) {
        return $html;
    }

    echo $html;

    return null;
}

/**
 * Closes snippet(…, slots: true) and renders it.
 */
function endsnippet(): ?string
{
    return Snippet::end();
}

/**
 * Starts a slot of the open snippet, slot() is the default slot ($slot in the snippet).
 */
function slot(string $name = "default"): void
{
    Snippet::slot($name);
}

function endslot(): void
{
    Snippet::endSlot();
}

/**
 * The url of a content image converted to AVIF and cached in public/media/images/, e.g. /media/images/7abed…/hello.avif.
 * The file is a File, an url (`/blog/hello.jpg`), path (`blog/hello.jpg`), reference (`file://uid`) or filename
 * of the current page. Returns the file's url if it can't be converted (no Imagick/GD with AVIF, not an image,
 * not used anywhere) or already is a small WebP/AVIF (auto mode), unknown files are returned unchanged.
 *
 * @param int|null $maxFileSize roughly, in KB (null: auto)
 * @param int|null $quality 0–100 (null: auto, depending on the resolution)
 */
function optimized_content_image(File|string $file, ?int $maxFileSize = null, ?int $quality = null): string
{
    $app = App::instance();
    $resolved = $file instanceof File ? $file : $app->resolveFile($file, Page::current());

    if ($resolved === null) {
        return (string) $file;
    }

    return $app->imageOptimizer()->url($resolved, $maxFileSize, $quality);
}

/**
 * The html with every local <img> pointing to its optimized AVIF (see optimized_content_image()),
 * e.g. optimized_content_images(md($page->data()->content)).
 *
 * @param int|null $maxFileSize roughly, in KB (null: auto)
 * @param int|null $quality 0–100 (null: auto, depending on the resolution)
 */
function optimized_content_images(string $html, ?int $maxFileSize = null, ?int $quality = null): string
{
    return \Blueprint\ImageOptimizer::replaceImages(
        $html,
        static fn(string $path): string => optimized_content_image($path, $maxFileSize, $quality),
    );
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
