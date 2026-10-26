<?php

declare(strict_types=1);

namespace StaticSiteGenerator;

use Blueprint\App;
use Blueprint\Page;

/**
 * Renders the listed and unlisted pages (no drafts) into a folder of html files, like the app serves them:
 * `/` → index.html, `/blog/` and `/blog/post` → blog/index.html and blog/post/index.html.
 * Also writes the used files of the pages, 404.html, redirect pages (content/redirects.json and pages that redirect)
 * and copies public/ without the front controller and the panel.
 */
class StaticSiteGenerator
{
    // nothing of public/ that only the php app needs
    private const SKIPPED_PUBLIC_FILES = ["index.php", "panel"];

    /** @var string[] */
    public array $errors = [];
    public int $pages = 0;
    public int $files = 0;
    public int $redirects = 0;

    public function __construct(private readonly App $app, public readonly string $target)
    {
    }

    /**
     * Resolves the target folder (relative to the working directory) and checks that it can be (re)created.
     * Folders of the project (or a parent of it) can never be used.
     */
    public static function targetDir(string $path, bool $clear): string
    {
        $target = self::realPath(str_starts_with($path, "/") ? $path : getcwd() . "/$path");
        $app = App::instance();

        foreach ([$app->projectDir(), $app->contentDir(), $app->siteDir(), $app->publicDir()] as $dir) {
            $dir = self::realPath($dir);
            $isProjectDir = $dir === self::realPath($app->projectDir());

            if ($target === $dir || str_starts_with("$dir/", "$target/") || (!$isProjectDir && str_starts_with("$target/", "$dir/"))) {
                throw new \InvalidArgumentException("$target can't be the target folder, choose an own folder like static/");
            }
        }

        if (is_file($target)) {
            throw new \InvalidArgumentException("$target is a file");
        }

        if (is_dir($target) && !$clear) {
            throw new \InvalidArgumentException("$target already exists, use --clear-existing-target-folder to replace it");
        }

        return $target;
    }

    /**
     * The absolute path without symlinks, `.` and `..`, also for paths that don't exist yet (resolved from their existing parent).
     */
    private static function realPath(string $path): string
    {
        $segments = [];

        foreach (explode("/", $path) as $segment) {
            match ($segment) {
                "", "." => null,
                ".." => array_pop($segments),
                default => $segments[] = $segment,
            };
        }

        $path = "/" . implode("/", $segments);
        $real = realpath($path);

        if ($real !== false || $path === "/") {
            return rtrim((string) $real, "/");
        }

        return self::realPath(dirname($path)) . "/" . basename($path);
    }

    public function build(): void
    {
        self::remove($this->target);
        mkdir($this->target, 0777, true);

        $this->writePages($this->app->site()->children());
        $this->write("404.html", (string) $this->app->notFoundResponse()->getBody());

        foreach ($this->app->redirects()->all() as $path => $redirect) {
            $this->writeRedirect("/$path", $redirect["url"]);
        }

        // after the pages, their rendering creates the optimized images in public/media
        $this->copyPublicDir($this->app->publicDir(), $this->target);
    }

    private function writePages(iterable $pages): void
    {
        foreach ($pages as $page) {
            $this->writePage($page);
            $this->writePages($page->children());
        }
    }

    private function writePage(Page $page): void
    {
        $response = $this->app->request($page->url());
        $status = $response->getStatusCode();

        if ($status === 200) {
            $this->write(self::htmlPath($page->url()), (string) $response->getBody());
            $this->pages++;
        } elseif ($status >= 300 && $status < 400 && $response->hasHeader("Location")) {
            $this->writeRedirect($page->url(), $response->getHeaderLine("Location"));
        } else {
            $this->errors[] = "{$page->url()}: status $status";
        }

        foreach ($page->files() as $file) {
            if ($file->isUsed()) {
                $this->copy($file->root, $file->id());
                $this->files++;
            }
        }
    }

    private function writeRedirect(string $path, string $url): void
    {
        $url = esc($url);
        $this->write(self::htmlPath($path), "<!doctype html>\n<meta charset=\"utf-8\">\n<title>Redirect</title>\n"
            . "<meta http-equiv=\"refresh\" content=\"0; url=$url\">\n<link rel=\"canonical\" href=\"$url\">\n"
            . "<a href=\"$url\">$url</a>\n");
        $this->redirects++;
    }

    /**
     * Urls without a file extension become folders with an index.html.
     */
    private static function htmlPath(string $url): string
    {
        $path = trim(rawurldecode($url), "/");

        if (pathinfo($path, PATHINFO_EXTENSION) !== "") {
            return $path;
        }

        return ltrim("$path/index.html", "/");
    }

    /**
     * Copies public/ recursively, without dot files and without overwriting the rendered pages.
     */
    private function copyPublicDir(string $source, string $target, bool $isRoot = true): void
    {
        foreach (scandir($source) ?: [] as $name) {
            if (str_starts_with($name, ".") || ($isRoot && in_array($name, self::SKIPPED_PUBLIC_FILES, true))) {
                continue;
            }

            if (is_dir("$source/$name")) {
                $this->copyPublicDir("$source/$name", "$target/$name", false);
            } elseif (!file_exists("$target/$name")) {
                $this->copy("$source/$name", substr("$target/$name", strlen($this->target) + 1));
            }
        }
    }

    private function write(string $path, string $contents): void
    {
        $file = "$this->target/$path";
        is_dir(dirname($file)) || mkdir(dirname($file), 0777, true);
        file_put_contents($file, $contents);
    }

    private function copy(string $source, string $path): void
    {
        $file = "$this->target/$path";
        is_dir(dirname($file)) || mkdir(dirname($file), 0777, true);
        copy($source, $file);
    }

    private static function remove(string $path): void
    {
        if (is_dir($path) && !is_link($path)) {
            foreach (array_diff(scandir($path) ?: [], [".", ".."]) as $name) {
                self::remove("$path/$name");
            }

            rmdir($path);
        } elseif (file_exists($path) || is_link($path)) {
            unlink($path);
        }
    }
}
