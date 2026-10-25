<?php

declare(strict_types=1);

namespace Blueprint;

use Dumbo\Context;
use Dumbo\Dumbo;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class App
{
    private static ?App $instance = null;

    private Dumbo $router;
    private ?Site $site = null;
    private ?Redirects $redirects = null;

    public function __construct(public readonly Config $config)
    {
        $this->router = new Dumbo($this->isDebug() ? Dumbo::ENV_DEVELOPMENT : Dumbo::ENV_PRODUCTION);
        // trailing slashes are part of a page's canonical url, see Page::url()
        $this->router->setRemoveTrailingSlash(false);
        $this->router->on(["GET", "HEAD"], "*", $this->handlePage(...));
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

        return self::$instance = $app;
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

    public function publicDir(): string
    {
        return $this->projectDir() . "/public";
    }

    public function site(): Site
    {
        return $this->site ??= new Site($this->contentDir());
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
    public function request(string $uri, string $method = "GET"): ResponseInterface
    {
        $request = new ServerRequest($method, $uri);
        parse_str($request->getUri()->getQuery(), $query);

        return $this->handle($request->withQueryParams($query));
    }

    private function configurePhp(): void
    {
        error_reporting($this->isDebug() ? E_ALL : E_ALL & ~E_DEPRECATED & ~E_NOTICE);
        ini_set("display_errors", $this->isDebug() ? "1" : "0");
    }

    private function handlePage(Context $context): ResponseInterface
    {
        $uri = $context->req->raw()->getUri();
        $path = rawurldecode($uri->getPath());

        try {
            if ($redirect = $this->redirects()->find($path)) {
                throw $redirect;
            }

            $page = $this->site()->find($path);

            if ($page === null) {
                return $this->notFoundResponse();
            }

            if ($page->url() !== $path) {
                $query = $uri->getQuery();
                throw new Redirect($page->url() . ($query === "" ? "" : "?$query"), 301);
            }

            return new Response(200, ["Content-Type" => "text/html; charset=UTF-8"], $this->renderPage($page));
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

        return $this->renderTemplate($page->template(), ["page" => $page]);
    }

    /**
     * Renders site/templates/{name}.php, falls back to the default template.
     */
    public function renderTemplate(string $name, array $variables = []): string
    {
        return $this->capture($this->templateFile($name), $variables + ["site" => $this->site()])[0];
    }

    public function templateFile(string $name): string
    {
        $dir = $this->siteDir() . "/templates";

        foreach ([$name, "default"] as $template) {
            if (preg_match('/^[\w-]+$/', $template) && is_file("$dir/$template.php")) {
                return "$dir/$template.php";
            }
        }

        return __DIR__ . "/views/default.php";
    }

    /**
     * Renders site/templates/partials/{name}.php.
     */
    public function renderPartial(string $name, array $variables = []): string
    {
        $file = $this->siteDir() . "/templates/partials/$name.php";

        if (str_contains($name, "..") || !is_file($file)) {
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

    private function notFoundResponse(): ResponseInterface
    {
        $file = $this->siteDir() . "/templates/404.php";
        $html = is_file($file)
            ? $this->capture($file, ["page" => null, "site" => $this->site()])[0]
            : "<h1>404 Not Found</h1>";

        return new Response(404, ["Content-Type" => "text/html; charset=UTF-8"], $html);
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

        return new Response(500, ["Content-Type" => "text/html; charset=UTF-8"], $html);
    }
}
