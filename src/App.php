<?php

declare(strict_types=1);

namespace Blueprint;

use Dumbo\Context;
use Dumbo\Dumbo;
use Michelf\Markdown;
use Michelf\MarkdownExtra;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\Stream;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class App
{
    private static ?App $instance = null;

    private Dumbo $router;
    private ?Site $site = null;
    private ?Redirects $redirects = null;
    private ?ImageOptimizer $imageOptimizer = null;
    /** @var array<string, Plugin> */
    private array $plugins = [];
    private ?string $cspNonce = null;

    public function __construct(public readonly Config $config)
    {
        $this->router = new Dumbo($this->isDebug() ? Dumbo::ENV_DEVELOPMENT : Dumbo::ENV_PRODUCTION);
        // trailing slashes are part of a page's canonical url, see Page::url()
        $this->router->setRemoveTrailingSlash(false);
        $this->router->on(["GET", "HEAD", "POST", "PUT", "PATCH", "DELETE"], "*", $this->handleRequest(...));
        $this->router->notFound(fn(Context $context) => $this->notFoundResponse());
        $this->router->onError(fn(\Throwable $error) => $this->errorResponse($error));
    }

    /**
     * Combines the given (project) config with the site/config, the project config wins.
     */
    public static function create(Config $config): self
    {
        $siteConfigDir = $config->withFallback([])->path("siteFolder") . "/config";
        $config = $config->withFallback(Config::readFolder($siteConfigDir, Config::hostname()));

        $app = new self($config);
        $app->configurePhp();
        self::$instance = $app;

        // plugins may use the app (App::instance(), site(), …) when they are loaded
        $app->plugins = Plugin::scan($app->siteDir() . "/plugins");
        array_map(fn(Plugin $plugin) => $plugin->load(), $app->plugins);

        return $app;
    }

    public static function instance(): self
    {
        return self::$instance ?? throw new \LogicException("The app has not been created yet, use App::create()");
    }

    public function isDebug(): bool
    {
        return (bool) $this->config->get("debug");
    }

    public function projectDir(): string
    {
        return $this->config->projectDir;
    }

    public function contentDir(): string
    {
        return $this->config->path("contentFolder");
    }

    public function siteDir(): string
    {
        return $this->config->path("siteFolder");
    }

    /** @return array<string, Plugin> */
    public function plugins(): array
    {
        return $this->plugins;
    }

    /**
     * A folder of the site and the same folder of every plugin, e.g. siteDirs("templates"). The site comes first.
     *
     * @return string[]
     */
    public function siteDirs(string $folder): array
    {
        return [$this->siteDir() . "/$folder", ...array_map(fn(Plugin $plugin) => "$plugin->root/$folder", array_values($this->plugins))];
    }

    public function publicDir(): string
    {
        return $this->projectDir() . "/public";
    }

    public function site(): Site
    {
        return $this->site ??= new Site($this->contentDir());
    }

    public function imageOptimizer(): ImageOptimizer
    {
        return $this->imageOptimizer ??= new ImageOptimizer($this->publicDir());
    }

    /**
     * A file by url (`/blog/hello.jpg`), path (`blog/hello.jpg`), reference (`file://uid`)
     * or filename of the given page (`hello.jpg`). Drafts' files only for logged in users.
     */
    public function resolveFile(string $reference, ?Page $page = null): ?File
    {
        [$scheme, $uid] = Uid::parse($reference) ?? [null, null];

        if ($scheme === "file") {
            $file = $this->site()->findFileByUid($uid);

            return $file !== null && (!$this->isInDraft($file->page) || User::current() !== null) ? $file : null;
        }

        $path = trim(rawurldecode((string) parse_url($reference, PHP_URL_PATH)), "/");

        if (!str_contains($path, "/")) {
            return $page?->file($path);
        }

        return $this->site()->find(dirname($path))?->file(basename($path));
    }

    private function isInDraft(Page $page): bool
    {
        for ($current = $page; $current !== null; $current = $current->parent) {
            if ($current->isDraft) {
                return true;
            }
        }

        return false;
    }

    public function redirects(): Redirects
    {
        return $this->redirects ??= Redirects::fromFile($this->contentDir() . "/redirects.json");
    }

    public function run(): void
    {
        $this->router->run();
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->router->handle($request);
    }

    /**
     * Handles a request without a server, e.g. in tests.
     */
    public function request(string $uri, string $method = "GET", ?array $json = null, array $headers = []): ResponseInterface
    {
        $request = new ServerRequest($method, $uri, $headers, $json === null ? null : json_encode($json));
        parse_str($request->getUri()->getQuery(), $query);

        return $this->handle($request->withQueryParams($query));
    }

    private function configurePhp(): void
    {
        error_reporting($this->isDebug() ? E_ALL : E_ALL & ~E_DEPRECATED & ~E_NOTICE);
        ini_set("display_errors", $this->isDebug() ? "1" : "0");
        // websites that link the package without installing its dependencies use the dd() fallback in helpers.php
        if (class_exists(\Symfony\Component\VarDumper\VarDumper::class)) {
            Dumper::register();
        }
    }

    /**
     * Markdown to html, with Markdown Extra (tables, footnotes, definition lists, …) if `markdown.extra` is enabled.
     * Inline html is escaped unless `$markup` is true (only for trusted content), links with unsafe schemes
     * (`javascript:`, see Field::isSafeUrl()) point to `#`.
     */
    public function markdown(string $markdown, bool $markup = false): string
    {
        $parser = $this->config->get("markdown.extra") ? new MarkdownExtra() : new Markdown();
        $parser->no_markup = !$markup;
        $parser->url_filter_func = fn(string $url) => Field::isSafeUrl($url) ? $url : "#";

        return $parser->transform($markdown);
    }

    public function isPanelEnabled(): bool
    {
        return (bool) $this->config->get("panel");
    }

    /**
     * Forgets the cached page tree, e.g. after the panel changed pages.
     */
    public function refreshSite(): void
    {
        $this->site = null;
    }

    /**
     * The panel is independent of the page rendering, it only gets /panel requests if it is enabled.
     */
    private function handleRequest(Context $context): ResponseInterface
    {
        $request = $context->req->raw();

        if ($this->isPanelEnabled() && Panel::handles($request->getUri()->getPath())) {
            return (new Panel($this))->handle($request);
        }

        if (!in_array($request->getMethod(), ["GET", "HEAD"], true)) {
            return new Response(405, ["Allow" => "GET, HEAD"]);
        }

        return $this->handlePage($request);
    }

    private function handlePage(ServerRequestInterface $request): ResponseInterface
    {
        $uri = $request->getUri();
        $path = rawurldecode($uri->getPath());
        $this->cspNonce = null;

        try {
            if ($redirect = $this->redirects()->find($path)) {
                throw $redirect;
            }

            $page = $this->site()->find($path);

            if ($page === null) {
                $file = $this->findFile($path);

                return $file === null ? $this->notFoundResponse() : $this->fileResponse($file);
            }

            if ($page->url() !== $path) {
                $query = $uri->getQuery();
                throw new Redirect($page->url() . ($query === "" ? "" : "?$query"), 301);
            }

            $html = $this->renderPage($page);

            if ($this->config->get("optimizeContentImages")) {
                $html = optimized_content_images($html);
            }

            return $this->htmlResponse(200, $html);
        } catch (Redirect $redirect) {
            return new Response($redirect->status, ["Location" => $redirect->url]);
        } finally {
            Page::setCurrent(null);
        }
    }

    /**
     * Renders the page's own php file if it has one, otherwise its template.
     * A page php file may also return an array, which is merged into the page data
     * and rendered with the template.
     */
    public function renderPage(Page $page): string
    {
        Page::setCurrent($page);

        if ($file = $page->phpFile()) {
            [$output, $result] = $this->capture($file, ["page" => $page, "site" => $this->site()]);

            if (!is_array($result)) {
                return $output;
            }

            $page->mergeData($result);
        }

        return $this->renderTemplate($page->type(), ["page" => $page]);
    }

    /**
     * Renders site/templates/{name}.php (or a plugin's), falls back to page.php and default.php.
     */
    public function renderTemplate(string $name, array $variables = []): string
    {
        return $this->capture($this->templateFile($name), $variables + ["site" => $this->site()])[0];
    }

    public function templateFile(string $name): string
    {
        foreach ([$name, Page::DEFAULT_TYPE, "default"] as $template) {
            foreach ($this->siteDirs("templates") as $dir) {
                if (preg_match('/^[\w-]+$/', $template) && is_file("$dir/$template.php")) {
                    return "$dir/$template.php";
                }
            }
        }

        return __DIR__ . "/views/default.php";
    }

    /**
     * Renders site/templates/snippets/{name}.php, a plugin's or the core src/views/{name}.php, e.g. "blocks/heading".
     * With a list of names the first existing snippet is used. Missing snippets render nothing (a comment in debug mode).
     */
    public function renderSnippet(string|array $name, array $variables = []): string
    {
        foreach ((array) $name as $candidate) {
            if (!is_string($candidate) || !preg_match('#^[\w-]+(/[\w-]+)*$#', $candidate)) {
                continue;
            }

            $file = self::firstFile([...array_map(fn(string $dir) => "$dir/snippets/$candidate.php", $this->siteDirs("templates")), __DIR__ . "/views/$candidate.php"]);

            if ($file !== null) {
                return $this->capture($file, $variables + ["page" => Page::current(), "site" => $this->site()])[0];
            }
        }

        return $this->isDebug() ? "<!-- snippet " . esc(implode(", ", (array) $name)) . " not found -->" : "";
    }

    /**
     * Renders site/templates/partials/{name}.php (or a plugin's), or an absolute path to a php file.
     */
    public function renderPartial(string $name, array $variables = []): string
    {
        $isPath = str_starts_with($name, "/") || preg_match('#^[a-z]:[\\\\/]#i', $name);
        $files = array_map(fn(string $dir) => "$dir/partials/$name.php", $this->siteDirs("templates"));
        $file = $isPath ? $name : (self::firstFile($files) ?? $files[0]);

        if ((!$isPath && str_contains($name, "..")) || !str_ends_with($file, ".php") || !is_file($file)) {
            throw new \InvalidArgumentException("Partial \"$name\" not found in $file");
        }

        return $this->capture($file, $variables + ["page" => Page::current(), "site" => $this->site()])[0];
    }

    /**
     * Includes a php file in an isolated scope.
     *
     * @return array{0: string, 1: mixed} the output and the return value
     */
    private function capture(string $file, array $variables): array
    {
        $level = ob_get_level();
        ob_start();

        try {
            $result = (static function () {
                extract(func_get_arg(1));

                return require func_get_arg(0);
            })($file, $variables);

            return [ob_get_clean(), $result];
        } finally {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
        }
    }

    /**
     * Files are served under their page's url, e.g. /blog/cover.jpg, if they are used (see File::isUsed()).
     */
    private function findFile(string $path): ?File
    {
        $path = trim($path, "/");
        $dir = dirname($path);

        if ($dir === "." || $dir === "") {
            return null;
        }

        $file = $this->site()->find($dir)?->file(basename($path));

        // unused files are only visible in the panel
        return $file !== null && (User::current() !== null || $file->isUsed()) ? $file : null;
    }

    /**
     * Other types are sent as application/octet-stream, so an uploaded file is never loaded as a script
     * or stylesheet of a page (the csp allows scripts from the site's origin).
     */
    private function fileResponse(File $file): ResponseInterface
    {
        $mime = $file->mime();
        $isInline = in_array($mime, File::INLINE_MIMES, true);

        return new Response(200, [
            "Content-Type" => $isInline ? $mime : "application/octet-stream",
            "Content-Length" => (string) $file->size(),
            "Content-Disposition" => ($isInline ? "inline" : "attachment") . "; filename=\"" . $file->filename() . "\"",
            "X-Content-Type-Options" => "nosniff",
            "Content-Security-Policy" => "default-src 'none'; sandbox",
            "Last-Modified" => gmdate("D, d M Y H:i:s", (int) filemtime($file->root)) . " GMT",
        ], Stream::create(fopen($file->root, "rb")));
    }

    public function notFoundResponse(): ResponseInterface
    {
        $file = self::firstFile(array_map(fn(string $dir) => "$dir/404.php", $this->siteDirs("templates")));
        $html = $file !== null
            ? $this->capture($file, ["page" => null, "site" => $this->site()])[0]
            : "<h1>404 Not Found</h1>";

        return $this->htmlResponse(404, $html);
    }

    /**
     * A rendered page with the security headers.
     */
    private function htmlResponse(int $status, string $html): ResponseInterface
    {
        $csp = $this->contentSecurityPolicy();

        return new Response($status, array_filter([
            "Content-Type" => "text/html; charset=UTF-8",
            "Content-Security-Policy" => $csp,
            "X-Content-Type-Options" => "nosniff",
        ]), $html);
    }

    /**
     * A random nonce for inline scripts of the current request: <script nonce="<?= csp_nonce() ?>">.
     */
    public function cspNonce(): string
    {
        return $this->cspNonce ??= base64_encode(random_bytes(16));
    }

    /**
     * The `csp` config as header value, with the nonce in script-src (or default-src) if a template used it.
     */
    public function contentSecurityPolicy(): ?string
    {
        $directives = $this->config->get("csp");

        if (!is_array($directives)) {
            return null;
        }

        $directives = array_filter($directives, fn($sources) => $sources === true || (is_string($sources) && $sources !== ""));

        if ($this->cspNonce !== null) {
            foreach (["script-src", "default-src"] as $name) {
                if (is_string($directives[$name] ?? null)) {
                    $directives["script-src"] = "$directives[$name] 'nonce-$this->cspNonce'";
                    break;
                }
            }
        }

        $policy = implode("; ", array_map(
            fn(string $name, string|bool $sources) => $sources === true ? $name : "$name $sources",
            array_keys($directives),
            $directives,
        ));

        return $policy !== "" ? $policy : null;
    }

    private static function firstFile(array $files): ?string
    {
        foreach ($files as $file) {
            if (is_file($file)) {
                return $file;
            }
        }

        return null;
    }

    private function errorResponse(\Throwable $error): ResponseInterface
    {
        error_log((string) $error);

        $html = $this->isDebug()
            ? sprintf(
                "<h1>%s</h1><p>%s</p><p>%s:%d</p><pre>%s</pre>",
                esc($error::class),
                esc($error->getMessage()),
                esc($error->getFile()),
                $error->getLine(),
                esc($error->getTraceAsString()),
            )
            : "<h1>500 Internal Server Error</h1>";

        return $this->htmlResponse(500, $html);
    }
}
