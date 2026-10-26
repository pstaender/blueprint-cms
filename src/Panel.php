<?php

declare(strict_types=1);

namespace Blueprint;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The admin panel: a Vue app (built into public/panel/) and its REST API under /panel/api.
 * It is independent of the page rendering, App only hands /panel requests to it when `panel` is enabled.
 *
 * Page ids in urls are slug paths, e.g. /panel/api/pages/blog/newest_entry/files. The slugs "children" and
 * "files" are reserved, they would be ambiguous.
 */
class Panel
{
    public const PATH = "/panel";

    public const RESERVED_SLUGS = ["children", "files"];

    /**
     * [method, path regex, handler]; the handler gets the request and the regex matches.
     * The first matching path wins (other methods → 405), so subroutes come before the page routes.
     */
    private const ROUTES = [
        ["POST", "#^/session$#", "login"],
        ["GET", "#^/session$#", "session"],
        ["DELETE", "#^/session$#", "logout"],
        ["GET", "#^/site$#", "getSite"],
        ["PATCH", "#^/site$#", "updateSite"],
        ["POST", "#^/site/children$#", "createPage"],
        ["GET", "#^/pages$#", "listPages"],
        ["GET", "#^/pages/(.+?)/children$#", "pageChildren"],
        ["POST", "#^/pages/(.+?)/children$#", "createPage"],
        ["GET", "#^/pages/(.+?)/files$#", "listFiles"],
        ["POST", "#^/pages/(.+?)/files$#", "uploadFile"],
        ["PATCH", "#^/pages/(.+?)/files$#", "sortFiles"],
        ["GET", "#^/pages/(.+?)/files/([^/]+)$#", "getFile"],
        ["PATCH", "#^/pages/(.+?)/files/([^/]+)$#", "updateFile"],
        ["PUT", "#^/pages/(.+?)/files/([^/]+)$#", "replaceFile"],
        ["DELETE", "#^/pages/(.+?)/files/([^/]+)$#", "deleteFile"],
        ["GET", "#^/pages/(.+)$#", "getPage"],
        ["PATCH", "#^/pages/(.+)$#", "updatePage"],
        ["DELETE", "#^/pages/(.+)$#", "deletePage"],
        ["GET", "#^/blueprints/pages$#", "pageBlueprints"],
        ["GET", "#^/roles$#", "listRoles"],
        ["GET", "#^/users$#", "listUsers"],
        ["POST", "#^/users$#", "createUser"],
        ["GET", "#^/users/([^/]+)$#", "getUser"],
        ["PATCH", "#^/users/([^/]+)$#", "updateUser"],
        ["DELETE", "#^/users/([^/]+)$#", "deleteUser"],
    ];

    public function __construct(private readonly App $app)
    {
    }

    public static function handles(string $path): bool
    {
        return $path === self::PATH || str_starts_with($path, self::PATH . "/");
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $path = rawurldecode($request->getUri()->getPath());
        $apiPath = self::PATH . "/api";

        if ($path !== $apiPath && !str_starts_with($path, "$apiPath/")) {
            return $this->appShell($request->getMethod());
        }

        try {
            return $this->dispatch($request, substr($path, strlen($apiPath)) ?: "/");
        } catch (ApiError $error) {
            return self::json(["error" => $error->getMessage(), ...$error->details], $error->status);
        } catch (\InvalidArgumentException | \LogicException | \RuntimeException $error) {
            return self::json(["error" => $error->getMessage()], 400);
        }
    }

    private function dispatch(ServerRequestInterface $request, string $path): ResponseInterface
    {
        $method = $request->getMethod();
        $matched = null;

        foreach (self::ROUTES as [$routeMethod, $pattern, $handler]) {
            if (($matched !== null && $pattern !== $matched) || !preg_match($pattern, $path, $matches)) {
                continue;
            }

            $matched = $pattern;

            if ($routeMethod !== $method) {
                continue;
            }

            if ($handler !== "login") {
                $this->authorize($request);
            }

            return $this->$handler($request, ...array_slice($matches, 1));
        }

        throw new ApiError($matched !== null ? "Method not allowed" : "Not found", $matched !== null ? 405 : 404);
    }

    /**
     * Every endpoint except the login needs a logged in user, changes also a valid CSRF token.
     */
    private function authorize(ServerRequestInterface $request): void
    {
        if (User::current() === null) {
            throw new ApiError("Unauthenticated", 401);
        }

        $isChange = !in_array($request->getMethod(), ["GET", "HEAD"], true);

        if ($isChange && !Session::isValidCsrfToken($request->getHeaderLine("X-CSRF-Token"))) {
            throw new ApiError("Invalid CSRF token", 403);
        }
    }

    /**
     * The built Vue app, it handles all /panel urls (history routing).
     */
    private function appShell(string $method): ResponseInterface
    {
        if (!in_array($method, ["GET", "HEAD"], true)) {
            return self::json(["error" => "Method not allowed"], 405);
        }

        $file = $this->app->publicDir() . self::PATH . "/index.html";

        if (!is_file($file)) {
            return new Response(503, ["Content-Type" => "text/html; charset=UTF-8"], "<h1>The panel is not built yet</h1><p>Run <code>npm install && npm run build</code> in <code>panel/</code>.</p>");
        }

        return new Response(200, ["Content-Type" => "text/html; charset=UTF-8"], (string) file_get_contents($file));
    }

    // Session

    private function login(ServerRequestInterface $request): ResponseInterface
    {
        $input = self::input($request);
        $user = User::find((string) ($input["email"] ?? ""));

        if ($user === null || !$user->verifyPassword((string) ($input["password"] ?? ""))) {
            usleep(random_int(200_000, 400_000));
            throw new ApiError("Invalid email or password", 401);
        }

        Session::login($user);
        User::setCurrent($user);

        return $this->session();
    }

    private function session(): ResponseInterface
    {
        return self::json([
            "user" => User::current()->toArray(),
            "csrf" => Session::csrfToken(),
            "site" => ["title" => (string) site()->data()->name, "url" => "/"],
        ]);
    }

    private function logout(): ResponseInterface
    {
        Session::logout();

        return new Response(204);
    }

    // Site

    private function getSite(): ResponseInterface
    {
        $site = $this->app->site();

        return self::json([
            "title" => (string) ($site->data()->name ?? "Site"),
            "url" => "/",
            "blueprint" => $site->blueprint()->toArray(),
            "data" => $site->data()->toArray(),
            "children" => $site->allChildren()->map($this->pageSummary(...))->all(),
        ]);
    }

    private function updateSite(ServerRequestInterface $request): ResponseInterface
    {
        $site = $this->app->site();
        $input = self::input($request);
        self::checkConflicts($site->data()->toArray(), $input);
        $values = $this->validatedValues($site->blueprint(), $site->data()->toArray(), $input["data"] ?? []);
        $site->update($values);

        return $this->getSite();
    }

    // Pages

    private function listPages(ServerRequestInterface $request): ResponseInterface
    {
        $search = mb_strtolower((string) ($request->getQueryParams()["search"] ?? ""));
        $pages = [];
        $collect = function (iterable $children) use (&$collect, &$pages, $search) {
            foreach ($children as $page) {
                if ($search === "" || str_contains(mb_strtolower($page->id() . " " . $page->title()->value()), $search)) {
                    $pages[] = $this->pageSummary($page);
                }
                $collect($page->allChildren());
            }
        };
        $collect($this->app->site()->allChildren());

        return self::json($pages);
    }

    private function getPage(ServerRequestInterface $request, string $id): ResponseInterface
    {
        return self::json($this->pageDetails($this->findPage($id)));
    }

    private function pageChildren(ServerRequestInterface $request, string $id): ResponseInterface
    {
        return self::json($this->findPage($id)->allChildren()->map($this->pageSummary(...))->values()->all());
    }

    /**
     * Body: {"data": {...}} to save fields, {"contentFormat": "json"}, {"slug": "…"}, {"type": "…"}, {"status": "listed"} and/or {"num": 2}.
     */
    private function updatePage(ServerRequestInterface $request, string $id): ResponseInterface
    {
        $page = $this->findPage($id);
        $input = self::input($request);
        self::checkConflicts($page->data()->toArray(), $input);

        if (isset($input["data"]) && is_array($input["data"])) {
            $page->update($this->validatedValues($page->blueprint(), $page->data()->toArray(), $input["data"]));
        }

        if (isset($input["contentFormat"]) && $input["contentFormat"] !== $page->contentFormat()) {
            $page = $page->changeContentFormat((string) $input["contentFormat"]);
        }

        if (isset($input["type"]) && $input["type"] !== $page->type()) {
            $page->changeType((string) $input["type"]);
        }

        if (isset($input["slug"]) && $input["slug"] !== $page->slug) {
            $page = $page->changeSlug(self::unreservedSlug((string) $input["slug"]));
        }

        if (isset($input["status"]) && $input["status"] !== $page->status()) {
            $page = $page->changeStatus((string) $input["status"]);
        }

        if (isset($input["num"]) && $page->isListed()) {
            $page = $page->changeNum((int) $input["num"]);
        }

        $this->app->refreshSite();

        return self::json($this->pageDetails($this->findPage($page->id())));
    }

    private function deletePage(ServerRequestInterface $request, string $id): ResponseInterface
    {
        $this->findPage($id)->delete();
        $this->app->refreshSite();

        return new Response(204);
    }

    /**
     * Body: {"slug": "new-page", "title": "New page", "type": "article"}, creates a draft.
     */
    private function createPage(ServerRequestInterface $request, ?string $parentId = null): ResponseInterface
    {
        $parent = $parentId === null ? null : $this->findPage($parentId);
        $input = self::input($request);
        $title = trim((string) ($input["title"] ?? ""));
        $type = (string) ($input["type"] ?? Page::DEFAULT_TYPE);

        if ($title === "") {
            throw new ApiError("The title is required", 422, ["errors" => ["title" => ["Title is required"]]]);
        }

        $slug = (string) ($input["slug"] ?? "");
        $page = Page::create($parent, self::unreservedSlug($slug !== "" ? $slug : self::slug($title)), array_filter([
            "title" => $title,
            "type" => $type === Page::DEFAULT_TYPE ? null : $type,
        ]));
        $this->app->refreshSite();

        return self::json($this->pageDetails($this->findPage($page->id())), 201);
    }

    /**
     * The page types. `page` and `default` are only listed separately if both blueprints exist in
     * site/blueprints/pages, otherwise `default` is just the fallback of `page`.
     */
    private function pageBlueprints(): ResponseInterface
    {
        $types = array_map("basename", Blueprint::names("pages"));
        $hasBoth = Blueprint::file("pages/page", includeCore: false) !== null
            && Blueprint::file("pages/default", includeCore: false) !== null;

        if (!$hasBoth) {
            $types = array_unique([Page::DEFAULT_TYPE, ...array_diff($types, ["default", Page::DEFAULT_TYPE])]);
        }

        return self::json(array_values(array_map(function (string $type) {
            $blueprint = Blueprint::forType($type);

            return ["type" => $type, "title" => $blueprint->title(), "icon" => $blueprint->get("icon")];
        }, $types)));
    }

    private function findPage(string $id): Page
    {
        return $this->app->site()->find($id, includeDrafts: true)
            ?? throw new ApiError("Page \"$id\" not found", 404);
    }

    private function pageSummary(Page $page): array
    {
        return [
            "id" => $page->id(),
            "path" => $page->id(),
            "uid" => $page->uid(),
            "ref" => $page->uid() !== null ? "page://" . $page->uid() : $page->id(),
            "title" => (string) $page->title()->value(),
            "status" => $page->status(),
            "num" => $page->num,
            "url" => $page->url(),
            "type" => $page->type(),
            "icon" => $page->blueprint()->get("icon") ?? ($page->isHome() ? "home" : null),
            "isHome" => $page->isHome(),
            "hasChildren" => $page->allChildren()->isNotEmpty(),
        ];
    }

    private function pageDetails(Page $page): array
    {
        $breadcrumb = [];

        for ($parent = $page->parent; $parent !== null; $parent = $parent->parent) {
            array_unshift($breadcrumb, ["id" => $parent->id(), "title" => (string) $parent->title()->value()]);
        }

        return [
            ...$this->pageSummary($page),
            "isEditable" => $page->isEditable(),
            "contentFormat" => $page->contentFormat(),
            "canHaveChildren" => $page->isFolder(),
            "blueprint" => $page->blueprint()->toArray(),
            "data" => $page->data()->toArray(),
            "breadcrumb" => $breadcrumb,
            "children" => $page->allChildren()->map($this->pageSummary(...))->all(),
            "files" => $page->files()->map($this->fileSummary(...))->all(),
        ];
    }

    // Files

    private function listFiles(ServerRequestInterface $request, string $id): ResponseInterface
    {
        return self::json($this->findPage($id)->files()->map($this->fileSummary(...))->values()->all());
    }

    /**
     * The body is the file itself, e.g. POST /pages/blog/files?filename=cover.jpg&template=image
     */
    private function uploadFile(ServerRequestInterface $request, string $id): ResponseInterface
    {
        $page = $this->findPage($id);
        $query = $request->getQueryParams();
        $template = (string) ($query["template"] ?? "");

        if ($template !== "" && !preg_match('/^[\w-]+$/', $template)) {
            throw new ApiError("Invalid file template", 400);
        }

        $body = $request->getBody();
        $contents = $body->detach() ?? (string) $body;
        $file = File::create($page, (string) ($query["filename"] ?? ""), $contents, $template !== "" ? ["template" => $template] : []);

        return self::json($this->fileDetails($file), 201);
    }

    /**
     * Body: {"sort": ["b.jpg", "a.png"]}
     */
    private function sortFiles(ServerRequestInterface $request, string $id): ResponseInterface
    {
        $page = $this->findPage($id);
        $sort = self::input($request)["sort"] ?? null;

        if (!is_array($sort) || !array_is_list($sort)) {
            throw new ApiError("The sort order must be a list of filenames", 400);
        }

        $page->sortFiles(array_map("strval", $sort));

        return $this->listFiles($request, $id);
    }

    private function getFile(ServerRequestInterface $request, string $id, string $filename): ResponseInterface
    {
        return self::json($this->fileDetails($this->findFile($id, $filename)));
    }

    /**
     * Body: {"data": {...}} and/or {"name": "new-name"} (without extension).
     */
    private function updateFile(ServerRequestInterface $request, string $id, string $filename): ResponseInterface
    {
        $file = $this->findFile($id, $filename);
        $input = self::input($request);
        self::checkConflicts($file->data()->toArray(), $input);

        if (isset($input["data"]) && is_array($input["data"])) {
            $file->update($this->validatedValues($file->blueprint(), $file->data()->toArray(), $input["data"]));
        }

        if (isset($input["name"]) && $input["name"] !== $file->name()) {
            $file = $file->changeName((string) $input["name"]);
        }

        return self::json($this->fileDetails($file));
    }

    /**
     * The body is the new file, e.g. PUT /pages/blog/files/cover.jpg?filename=new-cover.jpg
     * (the extension has to stay the same).
     */
    private function replaceFile(ServerRequestInterface $request, string $id, string $filename): ResponseInterface
    {
        $file = $this->findFile($id, $filename);
        $extension = strtolower(pathinfo((string) ($request->getQueryParams()["filename"] ?? ""), PATHINFO_EXTENSION));

        if ($extension !== $file->extension()) {
            throw new ApiError("The new file must be a .{$file->extension()} file", 400);
        }

        $body = $request->getBody();
        $file->replace($body->detach() ?? (string) $body);

        return self::json($this->fileDetails($file));
    }

    private function deleteFile(ServerRequestInterface $request, string $id, string $filename): ResponseInterface
    {
        $this->findFile($id, $filename)->delete();

        return new Response(204);
    }

    private function findFile(string $id, string $filename): File
    {
        return $this->findPage($id)->file($filename) ?? throw new ApiError("File \"$filename\" not found", 404);
    }

    private function fileSummary(File $file): array
    {
        return [
            ...$file->toArray(),
            "page" => $file->page->id(),
            "ref" => $file->uid() !== null ? "file://" . $file->uid() : $file->filename(),
        ];
    }

    private function fileDetails(File $file): array
    {
        return [
            ...$this->fileSummary($file),
            "pageTitle" => (string) $file->page->title()->value(),
            "breadcrumb" => [
                ...$this->pageDetails($file->page)["breadcrumb"],
                ["id" => $file->page->id(), "title" => (string) $file->page->title()->value()],
            ],
            "blueprint" => $file->blueprint()->toArray(),
            "data" => $file->data()->toArray(),
        ];
    }

    /**
     * "children" and "files" would be ambiguous in page urls (/pages/blog/files).
     */
    private static function unreservedSlug(string $slug): string
    {
        if (in_array($slug, self::RESERVED_SLUGS, true)) {
            throw new ApiError("The slug \"$slug\" is reserved", 422, ["errors" => ["slug" => ["\"$slug\" is reserved"]]]);
        }

        return $slug;
    }

    private static function slug(string $title): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', "-", strtolower(\Illuminate\Support\Str::ascii($title))), "-");
    }

    // Users

    /**
     * [{name, title}], the title comes from the role's blueprint.
     */
    private function listRoles(): ResponseInterface
    {
        return self::json(array_map(fn(string $role) => [
            "name" => $role,
            "title" => Blueprint::file("users/$role") !== null ? Blueprint::load("users/$role")->title() : ucfirst($role),
        ], User::roles()));
    }

    /**
     * Non-admins only see themselves.
     */
    private function listUsers(): ResponseInterface
    {
        $current = User::current();
        $users = $current->isAdmin() ? User::all() : User::all()->filter(fn(User $user) => $user->isSame($current));

        return self::json($users->map(fn(User $user) => [...$user->toArray(), "ref" => "user://" . $user->uid()])->values()->all());
    }

    private function getUser(ServerRequestInterface $request, string $uid): ResponseInterface
    {
        $user = $this->findAccessibleUser($uid);

        return self::json([...$user->toArray(), "role" => $user->role(), "blueprint" => $user->blueprint()->toArray()]);
    }

    /**
     * Body: {"email": "…", "password": "…", "role": "editor"}
     */
    private function createUser(ServerRequestInterface $request): ResponseInterface
    {
        self::requireAdmin("Only admins can create users");
        $input = self::input($request);
        $user = User::create(
            (string) ($input["email"] ?? ""),
            (string) ($input["password"] ?? ""),
            (string) (($input["role"] ?? "") ?: User::DEFAULT_ROLE),
        );

        return $this->getUser($request, $user->uid())->withStatus(201);
    }

    /**
     * Body: {"data": {...}}, {"email": "…"}, {"role": "…"} and/or {"password": "…"}.
     */
    private function updateUser(ServerRequestInterface $request, string $uid): ResponseInterface
    {
        $user = $this->findAccessibleUser($uid);
        $input = self::input($request);
        $current = User::current();

        // password changes need the password of the logged in user, except for admins
        if (($input["password"] ?? "") !== "" && !$current->isAdmin() && !$current->verifyPassword((string) ($input["currentPassword"] ?? ""))) {
            throw new ApiError("The current password is wrong", 422, ["errors" => ["currentPassword" => ["The current password is wrong"]]]);
        }

        self::checkConflicts($user->data()->toArray(), $input);
        $values = isset($input["data"]) && is_array($input["data"])
            ? $this->validatedValues($user->blueprint(), $user->data()->toArray(), $input["data"])
            : [];

        if (isset($input["email"])) {
            $values["email"] = (string) $input["email"];
        }

        if (isset($input["role"]) && $input["role"] !== $user->role()) {
            self::requireAdmin("Only admins can change the role of a user");
            $values["role"] = (string) $input["role"];
        }

        $user = $user->update($values);

        if (($input["password"] ?? "") !== "") {
            $user = $user->changePassword((string) $input["password"]);
        }

        return $this->getUser($request, $user->uid());
    }

    private function deleteUser(ServerRequestInterface $request, string $uid): ResponseInterface
    {
        self::requireAdmin("Only admins can delete users");
        $user = $this->findUser($uid);

        if ($user->uid() === User::current()?->uid()) {
            throw new ApiError("You can't delete yourself", 400);
        }

        $user->delete();

        return new Response(204);
    }

    private static function requireAdmin(string $message): void
    {
        if (User::current()?->isAdmin() !== true) {
            throw new ApiError($message, 403);
        }
    }

    /**
     * Non-admins can only see and edit themselves.
     */
    private function findAccessibleUser(string $uid): User
    {
        $user = $this->findUser($uid);

        if (!User::current()->isAdmin() && !$user->isSame(User::current())) {
            throw new ApiError("Only admins can see other users", 403);
        }

        return $user;
    }

    private function findUser(string $uid): User
    {
        return User::find($uid) ?? throw new ApiError("User \"$uid\" not found", 404);
    }

    // Helpers

    /**
     * Only fields of the blueprint that store a value are saved, the merged data must be valid.
     */
    /**
     * Optimistic locking for saves: the client sends `modifiedAt` (from when it loaded the model) and `original`
     * (the loaded values of the fields in `data`). If the model was saved by someone else in the meantime, only
     * fields that have changed since then conflict (409). Without `modifiedAt` the save overwrites.
     */
    private static function checkConflicts(array $current, array $input): void
    {
        if (!isset($input["modifiedAt"]) || ($current["modifiedAt"] ?? null) === $input["modifiedAt"]) {
            return;
        }

        $original = is_array($input["original"] ?? null) ? $input["original"] : [];
        $conflicts = [];

        foreach (array_keys(is_array($input["data"] ?? null) ? $input["data"] : []) as $name) {
            if (json_encode($current[$name] ?? null) !== json_encode($original[$name] ?? null)) {
                $conflicts[] = $name;
            }
        }

        if ($conflicts !== []) {
            throw new ApiError("Someone else has changed this in the meantime", 409, ["conflicts" => $conflicts]);
        }
    }

    private function validatedValues(Blueprint $blueprint, array $current, mixed $input): array
    {
        $values = [];

        foreach ($blueprint->fields() as $name => $field) {
            if ($field->hasValue() && $field->get("disabled") !== true && is_array($input) && array_key_exists($name, $input)) {
                $values[$name] = $input[$name] === "" || $input[$name] === [] ? null : $input[$name];
            }
        }

        if ($errors = $blueprint->validate([...$current, ...$values])) {
            throw new ApiError("Please check the highlighted fields", 422, ["errors" => $errors]);
        }

        return $values;
    }

    private static function input(ServerRequestInterface $request): array
    {
        $body = (string) $request->getBody();

        if ($body === "") {
            return [];
        }

        $input = json_decode($body, true);

        if (!is_array($input)) {
            throw new ApiError("Invalid JSON body", 400);
        }

        return $input;
    }

    private static function json(mixed $data, int $status = 200): ResponseInterface
    {
        return new Response(
            $status,
            ["Content-Type" => "application/json; charset=UTF-8", "Cache-Control" => "no-store"],
            json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        );
    }
}
