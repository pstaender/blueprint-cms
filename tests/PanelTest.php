<?php

use Blueprint\App;
use Blueprint\User;

beforeEach(function () {
    $this->dir = copyProject(BLUEPRINT_PROJECT);
    $this->app = createApp($this->dir);
    User::create("panel@example.com", "secret-password", "editor");
});

afterEach(fn() => removeProject($this->dir));

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

it("requires a login", function () {
    expect(api($this->app, "GET", "/site")[0])->toBe(401)
        ->and(api($this->app, "POST", "/session", ["email" => "panel@example.com", "password" => "wrong"])[0])->toBe(401);
});

it("logs in and out", function () {
    $csrf = login($this->app);
    [$status, $session] = api($this->app, "GET", "/session");

    expect($status)->toBe(200)
        ->and($session["user"]["email"])->toBe("panel@example.com")
        ->and($session["user"])->not->toHaveKey("password")
        ->and($session["csrf"])->toBe($csrf);

    api($this->app, "DELETE", "/session", csrf: $csrf);

    expect(api($this->app, "GET", "/site")[0])->toBe(401);
});

it("requires a csrf token for changes", function () {
    login($this->app);

    expect(api($this->app, "PATCH", "/site", ["data" => ["name" => "New"]])[0])->toBe(403)
        ->and(api($this->app, "PATCH", "/site", ["data" => ["name" => "New"]], "wrong")[0])->toBe(403);
});

it("reads and updates the site", function () {
    $csrf = login($this->app);
    [, $site] = api($this->app, "GET", "/site");

    expect($site["title"])->toBe("Blueprint fixture")
        ->and($site["blueprint"]["title"])->toBe("Website")
        ->and(array_column($site["children"], "id"))->toContain("about", "home");

    [$status, $result] = api($this->app, "PATCH", "/site", ["data" => ["name" => ""]], $csrf);
    expect($status)->toBe(422)->and($result["errors"])->toHaveKey("name");

    [$status, $site] = api($this->app, "PATCH", "/site", ["data" => ["name" => "Renamed", "unknown" => "x", "slogan" => "x"]], $csrf);
    expect($status)->toBe(200)
        ->and(array_keys($site["data"]))->toBe(["createdAt", "modifiedAt", "name"])
        ->and($site["data"]["name"])->toBe("Renamed")
        ->and($site["data"])->not->toHaveKey("slogan");
});

it("reads pages", function () {
    login($this->app);
    [$status, $page] = api($this->app, "GET", "/pages/showcase");

    expect($status)->toBe(200)
        ->and($page["title"])->toBe("Fish & Chips <b>")
        ->and($page["status"])->toBe("unlisted")
        ->and($page["blueprint"]["name"])->toBe("pages/showcase")
        ->and($page["data"]["category"])->toBe("review")
        ->and(api($this->app, "GET", "/pages/nope")[0])->toBe(404)
        ->and(array_column(api($this->app, "GET", "/pages?search=fish")[1], "id"))->toBe(["showcase"]);
});

it("updates pages and keeps data that isn't in the blueprint", function () {
    $csrf = login($this->app);
    [$status, $page] = api($this->app, "PATCH", "/pages/showcase", ["data" => ["title" => "New title", "price" => 3, "template" => "x"]], $csrf);

    expect($status)->toBe(200)
        ->and($page["data"]["title"])->toBe("New title")
        ->and($page["data"]["price"])->toBe(3)
        ->and($page["data"]["type"])->toBe("showcase")
        ->and($page["data"]["undefinedField"])->toBe("<i>not in blueprint</i>");

    [$status, $result] = api($this->app, "PATCH", "/pages/about", ["data" => ["title" => ""]], $csrf);
    expect($status)->toBe(422)->and($result["errors"]["title"])->toBe(["Title is required"]);
});

it("creates, publishes, sorts and deletes pages", function () {
    $csrf = login($this->app);

    [$status, $page] = api($this->app, "POST", "/site/children", ["title" => "Über uns!", "type" => "article"], $csrf);
    expect($status)->toBe(201)
        ->and($page["id"])->toBe("uber-uns")
        ->and($page["status"])->toBe("draft")
        ->and($page["type"])->toBe("article")
        ->and(is_file("$this->dir/content/.uber-uns/page.json"))->toBeTrue();

    [, $page] = api($this->app, "PATCH", "/pages/uber-uns", ["status" => "listed"], $csrf);
    expect($page["status"])->toBe("listed")->and($page["num"])->toBe(1);

    [, $child] = api($this->app, "POST", "/pages/uber-uns/children", ["title" => "Team", "slug" => "team"], $csrf);
    expect($child["id"])->toBe("uber-uns/team")
        ->and(is_file("$this->dir/content/01_uber-uns/.team/page.json"))->toBeTrue()
        ->and(api($this->app, "GET", "/pages/uber-uns/team")[1]["breadcrumb"][0]["id"])->toBe("uber-uns")
        ->and(api($this->app, "GET", "/pages/uber-uns/team/files")[1])->toBe([])
        ->and(api($this->app, "GET", "/pages/uber-uns/team/children")[1])->toBe([])
        ->and(api($this->app, "DELETE", "/pages/uber-uns/team/files", csrf: $csrf)[0])->toBe(405)
        ->and(api($this->app, "POST", "/pages/uber-uns/children", ["title" => "Files"], $csrf)[0])->toBe(422);

    api($this->app, "PATCH", "/pages/about", ["status" => "listed"], $csrf);
    [, $about] = api($this->app, "PATCH", "/pages/about", ["num" => 1], $csrf);
    expect($about["num"])->toBe(1)
        ->and(is_dir("$this->dir/content/02_uber-uns"))->toBeTrue();

    expect(api($this->app, "POST", "/site/children", ["title" => "About"], $csrf)[0])->toBe(400)
        ->and(api($this->app, "PATCH", "/pages/home", ["status" => "draft"], $csrf)[0])->toBe(400);

    expect($this->app->request("/panel/api/pages/uber-uns", "DELETE", null, ["X-CSRF-Token" => $csrf])->getStatusCode())->toBe(204)
        ->and(file_exists("$this->dir/content/02_uber-uns"))->toBeFalse();
});

it("manages users", function () {
    $csrf = login($this->app);

    [$status, $user] = api($this->app, "POST", "/users", ["email" => "new@example.com", "password" => "long-enough"], $csrf);
    expect($status)->toBe(201)
        ->and($user["role"])->toBe("user")
        ->and(api($this->app, "POST", "/users", ["email" => "x@example.com", "password" => "short"], $csrf)[0])->toBe(400)
        ->and(api($this->app, "POST", "/users", ["email" => "y@example.com", "password" => "long-enough", "role" => "editor"], $csrf)[0])->toBe(403)
        ->and(api($this->app, "PATCH", "/users/{$user["uid"]}", ["role" => "editor"], $csrf)[0])->toBe(403)
        ->and(api($this->app, "PATCH", "/users/{$user["uid"]}", ["role" => "user", "email" => "new@example.com"], $csrf)[0])->toBe(200);

    $path = "/users/{$user["uid"]}";
    [$status, $result] = api($this->app, "PATCH", $path, ["password" => "another-password", "currentPassword" => "wrong"], $csrf);
    expect($status)->toBe(422)
        ->and($result["errors"])->toHaveKey("currentPassword")
        ->and(api($this->app, "PATCH", $path, ["password" => "another-password"], $csrf)[0])->toBe(422);

    [, $user] = api($this->app, "PATCH", $path, ["data" => ["name" => "Hi"], "password" => "another-password", "currentPassword" => "secret-password"], $csrf);
    expect($user["name"])->toBe("Hi")
        ->and(User::find("new@example.com")->verifyPassword("another-password"))->toBeTrue()
        ->and(array_column(api($this->app, "GET", "/users")[1], "email"))->toContain("new@example.com")
        ->and(api($this->app, "DELETE", "/users/" . User::find("panel@example.com")->uid(), csrf: $csrf)[0])->toBe(400);

    User::create("chief@example.com", "admin-password", "admin");
    $csrf = login($this->app, "chief@example.com", "admin-password");
    [$status, $user] = api($this->app, "PATCH", $path, ["role" => "editor"], $csrf);

    expect($status)->toBe(200)
        ->and($user["blueprint"]["name"])->toBe("users/editor")
        ->and(api($this->app, "POST", "/users", ["email" => "y@example.com", "password" => "long-enough", "role" => "editor"], $csrf)[0])->toBe(201)
        ->and(api($this->app, "PATCH", $path, ["password" => "admin-changed"], $csrf)[0])->toBe(200)
        ->and(User::find("new@example.com")->verifyPassword("admin-changed"))->toBeTrue()
        ->and(User::find("chief@example.com")->isAdmin())->toBeTrue()
        ->and(User::find("chief@example.com")->blueprint()->name)->toBe("users/admin");
});

it("makes the only user an admin", function () {
    file_put_contents("$this->dir/content/users.json", '[{"email": "solo@example.com", "role": "editor"}]');

    expect(User::find("solo@example.com")->role())->toBe("admin")
        ->and(User::find("solo@example.com")->toArray()["role"])->toBe("admin");

    User::create("second@example.com", "secret-password");

    expect(User::find("solo@example.com")->role())->toBe("admin")
        ->and(User::find("second@example.com")->role())->toBe("user");

    unlink("$this->dir/content/users.json");

    expect(User::create("first@example.com", "secret-password")->data->role)->toBe("admin");
});

it("serves the panel app for all panel urls", function () {
    $response = $this->app->request("/panel/pages/about");

    expect($response->getStatusCode())->toBeIn([200, 503])
        ->and(api($this->app, "GET", "/nope")[0])->toBe(404);
});

it("doesn't handle /panel when the panel is disabled", function () {
    $app = createApp($this->dir, ["panel" => false]);

    expect($app->request("/panel")->getStatusCode())->toBe(404)
        ->and($app->request("/panel/api/session", "POST")->getStatusCode())->toBe(405)
        ->and($app->request("/about")->getStatusCode())->toBe(200);
});

it("changes slug and type of pages", function () {
    $csrf = login($this->app);
    [$status, $page] = api($this->app, "PATCH", "/pages/about", ["slug" => "about-us", "type" => "article"], $csrf);

    expect($status)->toBe(200)
        ->and($page["id"])->toBe("about-us")
        ->and($page["type"])->toBe("article")
        ->and($page["blueprint"]["name"])->toBe("pages/article")
        ->and($page["uid"])->toMatch('/^[a-z0-9]{16}$/')
        ->and($page["ref"])->toBe("page://" . $page["uid"])
        ->and(api($this->app, "PATCH", "/pages/showcase", ["slug" => "about-us"], $csrf)[0])->toBe(400);
});

it("lists children for the page tree", function () {
    login($this->app);
    [$status, $children] = api($this->app, "GET", "/pages/builder/children");

    expect($status)->toBe(200)->and($children)->toBe([]);
});

it("uploads, updates and deletes files", function () {
    $csrf = login($this->app);
    $png = file_get_contents("$this->dir/content/builder/photo.png");

    $response = $this->app->request("/panel/api/pages/builder/files?filename=New%20Image.PNG&template=image", "POST", null, ["X-CSRF-Token" => $csrf]);
    expect($response->getStatusCode())->toBe(400);

    $request = (new Nyholm\Psr7\ServerRequest("POST", "/panel/api/pages/builder/files?filename=New%20Image.PNG&template=image", ["X-CSRF-Token" => $csrf], $png))
        ->withQueryParams(["filename" => "New Image.PNG", "template" => "image"]);
    $response = $this->app->handle($request);
    $file = json_decode(body($response), true);

    expect($response->getStatusCode())->toBe(201)
        ->and($file["filename"])->toBe("new-image.png")
        ->and($file["template"])->toBe("image")
        ->and($file["ref"])->toBe("file://" . $file["uid"])
        ->and($file["blueprint"]["name"])->toBe("files/image");

    [$status, $file] = api($this->app, "PATCH", "/pages/builder/files/new-image.png", ["data" => ["alt" => "Alt"], "name" => "renamed"], $csrf);
    expect($status)->toBe(200)
        ->and($file["filename"])->toBe("renamed.png")
        ->and($file["data"]["alt"])->toBe("Alt");

    [, $page] = api($this->app, "GET", "/pages/builder");
    expect(array_column($page["files"], "filename"))->toContain("renamed.png");

    expect(api($this->app, "DELETE", "/pages/builder/files/renamed.png", csrf: $csrf)[0])->toBe(204)
        ->and(api($this->app, "GET", "/pages/builder/files/renamed.png")[0])->toBe(404);
});

it("replaces files", function () {
    $csrf = login($this->app);
    page("builder")->file("notes.txt")->update(["createdAt" => "2026-01-01T10:00:00+00:00", "modifiedAt" => "2026-01-01T10:00:00+00:00"]);
    $uid = page("builder")->file("notes.txt")->uid();
    $replace = fn(string $filename, string $contents) => $this->app->handle(
        (new Nyholm\Psr7\ServerRequest("PUT", "/panel/api/pages/builder/files/notes.txt", ["X-CSRF-Token" => $csrf], $contents))
            ->withQueryParams(["filename" => $filename])
    );

    $response = $replace("New Notes.TXT", "new notes");
    $file = json_decode(body($response), true);

    expect($response->getStatusCode())->toBe(200)
        ->and($file["filename"])->toBe("notes.txt")
        ->and($file["uid"])->toBe($uid)
        ->and($file["data"]["createdAt"])->toBe("2026-01-01T10:00:00+00:00")
        ->and($file["data"]["modifiedAt"])->not->toBe("2026-01-01T10:00:00+00:00")
        ->and(file_get_contents("$this->dir/content/builder/notes.txt"))->toBe("new notes")
        ->and($replace("notes.pdf", "x")->getStatusCode())->toBe(400)
        ->and(file_get_contents("$this->dir/content/builder/notes.txt"))->toBe("new notes");
});

it("restores a replaced file that isn't accepted", function () {
    $file = page("builder")->file("photo.png");
    $png = file_get_contents($file->root);

    expect(fn() => $file->replace("not an image"))->toThrow(InvalidArgumentException::class)
        ->and(file_get_contents($file->root))->toBe($png)
        ->and(glob("$this->dir/content/builder/.*replaced"))->toBe([]);
});

it("sorts files", function () {
    $csrf = login($this->app);

    [$status, $files] = api($this->app, "PATCH", "/pages/builder/files", ["sort" => ["photo.png", "notes.txt"]], $csrf);

    expect($status)->toBe(200)
        ->and(array_column($files, "filename"))->toBe(["photo.png", "notes.txt"])
        ->and(page("builder")->file("notes.txt")->data()->sort)->toBe(2)
        ->and(api($this->app, "PATCH", "/pages/builder/files", ["sort" => "x"], $csrf)[0])->toBe(400);

    [, $files] = api($this->app, "PATCH", "/pages/builder/files", ["sort" => ["notes.txt"]], $csrf);
    expect(array_column($files, "filename"))->toBe(["notes.txt", "photo.png"]);
});

it("lists the roles and only accepts existing roles", function () {
    User::create("chief@example.com", "admin-password", "admin");
    $csrf = login($this->app, "chief@example.com", "admin-password");
    [$status, $roles] = api($this->app, "GET", "/roles");
    $uid = User::find("panel@example.com")->uid();

    expect($status)->toBe(200)
        ->and($roles)->toBe([["name" => "user", "title" => "User"], ["name" => "admin", "title" => "Admin"], ["name" => "editor", "title" => "Editor"]])
        ->and(api($this->app, "POST", "/users", ["email" => "x@example.com", "password" => "long-enough", "role" => "boss"], $csrf)[0])->toBe(400)
        ->and(api($this->app, "PATCH", "/users/$uid", ["role" => "boss"], $csrf)[0])->toBe(400)
        ->and(api($this->app, "PATCH", "/users/$uid", ["role" => "user"], $csrf)[1]["role"])->toBe("user");
});

it("keeps a role that has no blueprint anymore", function () {
    $csrf = login($this->app);
    unlink("$this->dir/site/blueprints/users/editor.yml");
    $uid = User::find("panel@example.com")->uid();

    expect(User::roles())->toBe(["user", "admin"])
        ->and(api($this->app, "PATCH", "/users/$uid", ["role" => "editor", "data" => ["name" => "Panel"]], $csrf)[0])->toBe(200);
});

it("lists page and default as one type unless both blueprints exist", function () {
    login($this->app);
    $types = fn() => array_column(api($this->app, "GET", "/blueprints/pages")[1], "type");

    expect($types()[0])->toBe("page")
        ->and($types())->toContain("article", "builder")
        ->not->toContain("default");

    file_put_contents("$this->dir/site/blueprints/pages/page.yml", "title: Page\n");
    expect($types())->not->toContain("default");

    file_put_contents("$this->dir/site/blueprints/pages/default.yml", "title: Default\n");
    expect($types())->toContain("page", "default");
});

it("changes the content format", function () {
    $csrf = login($this->app);
    [$status, $page] = api($this->app, "PATCH", "/pages/about", ["contentFormat" => "md"], $csrf);

    expect($status)->toBe(200)
        ->and($page["contentFormat"])->toBe("md")
        ->and(is_file("$this->dir/content/about/page.md"))->toBeTrue()
        ->and(api($this->app, "PATCH", "/pages/about", ["contentFormat" => "xml"], $csrf)[0])->toBe(400);
});
