<?php

use Blueprint\App;
use Blueprint\User;

beforeEach(function () {
    $this->dir = copyProject(BLUEPRINT_PROJECT);
    $this->app = createApp($this->dir);
    User::create("panel@example.com", "secret-password", "editor");
});

afterEach(fn() => removeProject($this->dir));

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

    $home = array_values(array_filter($site["children"], fn ($page) => $page["isHome"]))[0];
    // the home page gets the icon of its blueprint (the default one: article), there's no house by default
    expect($home["id"])->toBe("home")->and($home["icon"])->toBe("📝");

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

it("moves pages to another parent", function () {
    $csrf = login($this->app);
    api($this->app, "PATCH", "/pages/about", ["status" => "listed"], $csrf);

    [$status, $page] = api($this->app, "PATCH", "/pages/about", ["parent" => "showcase"], $csrf);
    expect($status)->toBe(200)
        ->and($page["id"])->toBe("showcase/about")
        ->and($page["parent"])->toBe("showcase")
        ->and($page["status"])->toBe("listed")
        ->and(is_file("$this->dir/content/showcase/01_about/page.json"))->toBeTrue()
        ->and(api($this->app, "GET", "/pages/about")[0])->toBe(404);

    expect(api($this->app, "PATCH", "/pages/showcase", ["parent" => "showcase/about"], $csrf)[0])->toBe(400)
        ->and(api($this->app, "PATCH", "/pages/home", ["parent" => "showcase"], $csrf)[0])->toBe(400);

    [, $page] = api($this->app, "PATCH", "/pages/showcase/about", ["parent" => null, "status" => "draft"], $csrf);
    expect($page["id"])->toBe("about")
        ->and($page["parent"])->toBeNull()
        ->and(is_file("$this->dir/content/.about/page.json"))->toBeTrue();
});

it("creates, publishes, sorts and deletes pages", function () {
    $csrf = login($this->app);

    [$status, $page] = api($this->app, "POST", "/site/~children", ["title" => "Über uns!", "type" => "article"], $csrf);
    expect($status)->toBe(201)
        ->and($page["id"])->toBe("uber-uns")
        ->and($page["status"])->toBe("draft")
        ->and($page["type"])->toBe("article")
        ->and(is_file("$this->dir/content/.uber-uns/page.json"))->toBeTrue();

    [, $page] = api($this->app, "PATCH", "/pages/uber-uns", ["status" => "listed"], $csrf);
    expect($page["status"])->toBe("listed")->and($page["num"])->toBe(1);

    [, $child] = api($this->app, "POST", "/pages/uber-uns/~children", ["title" => "Team", "slug" => "team"], $csrf);
    expect($child["id"])->toBe("uber-uns/team")
        ->and(is_file("$this->dir/content/01_uber-uns/.team/page.json"))->toBeTrue()
        ->and(api($this->app, "GET", "/pages/uber-uns/team")[1]["breadcrumb"][0]["id"])->toBe("uber-uns")
        ->and(api($this->app, "GET", "/pages/uber-uns/team/~files")[1])->toBe([])
        ->and(api($this->app, "GET", "/pages/uber-uns/team/~children")[1])->toBe([])
        ->and(api($this->app, "DELETE", "/pages/uber-uns/team/~files", csrf: $csrf)[0])->toBe(405)
        ->and(api($this->app, "POST", "/pages/uber-uns/~children", ["title" => "Files"], $csrf)[0])->toBe(201)
        ->and(api($this->app, "GET", "/pages/uber-uns/files")[1]["title"])->toBe("Files");

    api($this->app, "PATCH", "/pages/about", ["status" => "listed"], $csrf);
    [, $about] = api($this->app, "PATCH", "/pages/about", ["num" => 1], $csrf);
    expect($about["num"])->toBe(1)
        ->and(is_dir("$this->dir/content/02_uber-uns"))->toBeTrue();

    expect(api($this->app, "POST", "/site/~children", ["title" => "About"], $csrf)[0])->toBe(400)
        ->and(api($this->app, "PATCH", "/pages/home", ["status" => "draft"], $csrf)[0])->toBe(400);

    expect($this->app->request("/panel/api/pages/uber-uns", "DELETE", null, ["X-CSRF-Token" => $csrf])->getStatusCode())->toBe(204)
        ->and(file_exists("$this->dir/content/02_uber-uns"))->toBeFalse();
});

it("lets non-admins only see and edit themselves", function () {
    $csrf = login($this->app);
    $own = "/users/" . User::find("panel@example.com")->uid();
    $other = "/users/editor1";

    expect(array_column(api($this->app, "GET", "/users")[1], "email"))->toBe(["panel@example.com"])
        ->and(api($this->app, "GET", $other)[0])->toBe(403)
        ->and(api($this->app, "PATCH", $other, ["data" => ["bio" => "x"]], $csrf)[0])->toBe(403)
        ->and(api($this->app, "DELETE", $other, csrf: $csrf)[0])->toBe(403)
        ->and(api($this->app, "POST", "/users", ["email" => "y@example.com", "password" => "long-enough"], $csrf)[0])->toBe(403)
        ->and(api($this->app, "PATCH", $own, ["role" => "admin"], $csrf)[0])->toBe(403)
        ->and(api($this->app, "PATCH", $own, ["role" => "editor", "email" => "panel@example.com"], $csrf)[0])->toBe(200);

    [$status, $result] = api($this->app, "PATCH", $own, ["password" => "another-password", "currentPassword" => "wrong"], $csrf);
    expect($status)->toBe(422)
        ->and($result["errors"])->toHaveKey("currentPassword")
        ->and(api($this->app, "PATCH", $own, ["password" => "another-password"], $csrf)[0])->toBe(422);

    [, $user] = api($this->app, "PATCH", $own, ["data" => ["bio" => "Hi"], "password" => "another-password", "currentPassword" => "secret-password"], $csrf);
    expect($user["bio"])->toBe("Hi")
        ->and(User::find("panel@example.com")->verifyPassword("another-password"))->toBeTrue();
});

it("lets admins manage users", function () {
    User::create("chief@example.com", "admin-password", "admin");
    $csrf = login($this->app, "chief@example.com", "admin-password");

    [$status, $user] = api($this->app, "POST", "/users", ["email" => "new@example.com", "password" => "long-enough", "role" => "editor"], $csrf);
    $path = "/users/{$user["uid"]}";

    expect($status)->toBe(201)
        ->and($user["blueprint"]["name"])->toBe("users/editor")
        ->and(api($this->app, "POST", "/users", ["email" => "x@example.com", "password" => "short"], $csrf)[0])->toBe(400)
        ->and(array_column(api($this->app, "GET", "/users")[1], "email"))->toContain("new@example.com", "editor@example.com")
        ->and(api($this->app, "PATCH", $path, ["role" => "user"], $csrf)[1]["role"])->toBe("user")
        ->and(api($this->app, "PATCH", $path, ["password" => "admin-changed"], $csrf)[0])->toBe(200)
        ->and(User::find("new@example.com")->verifyPassword("admin-changed"))->toBeTrue()
        ->and(User::find("chief@example.com")->blueprint()->name)->toBe("users/admin")
        ->and(api($this->app, "DELETE", "/users/" . User::find("chief@example.com")->uid(), csrf: $csrf)[0])->toBe(400)
        ->and(api($this->app, "DELETE", $path, csrf: $csrf)[0])->toBe(204);
});

it("detects conflicting changes with modifiedAt", function () {
    $csrf = login($this->app);
    [, $page] = api($this->app, "PATCH", "/pages/showcase", ["data" => ["title" => "Loaded", "text" => "Loaded text"]], $csrf);
    $loaded = $page["data"]["modifiedAt"];
    file_put_contents("$this->dir/content/showcase/page.json", json_encode([...$page["data"], "text" => "Their text", "modifiedAt" => "2000-01-01T00:00:00+00:00"]));
    $this->app->refreshSite();
    $save = fn(array $data, array $original) => api($this->app, "PATCH", "/pages/showcase", ["data" => $data, "original" => $original, "modifiedAt" => $loaded], $csrf);

    [$status, $result] = $save(["text" => "My text"], ["text" => "Loaded text"]);
    expect($status)->toBe(409)
        ->and($result["conflicts"])->toBe(["text"]);

    [$status, $page] = $save(["title" => "My title"], ["title" => "Loaded"]);
    expect($status)->toBe(200)
        ->and($page["data"]["title"])->toBe("My title")
        ->and($page["data"]["text"])->toBe("Their text");

    [$status, $page] = api($this->app, "PATCH", "/pages/showcase", ["data" => ["text" => "Overwritten"]], $csrf);
    expect($status)->toBe(200)->and($page["data"]["text"])->toBe("Overwritten");

    [$status] = api($this->app, "PATCH", "/pages/showcase", ["data" => ["text" => "Next"], "original" => ["text" => "x"], "modifiedAt" => $page["data"]["modifiedAt"]], $csrf);
    expect($status)->toBe(200);
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
        ->and($app->request("/panel/api/session", "POST")->getStatusCode())->toBe(404)
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
    [$status, $children] = api($this->app, "GET", "/pages/builder/~children");

    expect($status)->toBe(200)->and($children)->toBe([]);
});

it("uploads, updates and deletes files", function () {
    $csrf = login($this->app);
    $png = file_get_contents("$this->dir/content/builder/photo.png");

    $response = $this->app->request("/panel/api/pages/builder/~files?filename=New%20Image.PNG&template=image", "POST", null, ["X-CSRF-Token" => $csrf]);
    expect($response->getStatusCode())->toBe(400);

    $request = (new Nyholm\Psr7\ServerRequest("POST", "/panel/api/pages/builder/~files?filename=New%20Image.PNG&template=image", ["X-CSRF-Token" => $csrf], $png))
        ->withQueryParams(["filename" => "New Image.PNG", "template" => "image"]);
    $response = $this->app->handle($request);
    $file = json_decode(body($response), true);

    expect($response->getStatusCode())->toBe(201)
        ->and($file["filename"])->toBe("new-image.png")
        ->and($file["template"])->toBe("image")
        ->and($file["ref"])->toBe("file://" . $file["uid"])
        ->and($file["blueprint"]["name"])->toBe("files/image");

    [$status, $file] = api($this->app, "PATCH", "/pages/builder/~files/new-image.png", ["data" => ["alt" => "Alt"], "name" => "renamed"], $csrf);
    expect($status)->toBe(200)
        ->and($file["filename"])->toBe("renamed.png")
        ->and($file["data"]["alt"])->toBe("Alt");

    [, $page] = api($this->app, "GET", "/pages/builder");
    expect(array_column($page["files"], "filename"))->toContain("renamed.png");

    expect(api($this->app, "DELETE", "/pages/builder/~files/renamed.png", csrf: $csrf)[0])->toBe(204)
        ->and(api($this->app, "GET", "/pages/builder/~files/renamed.png")[0])->toBe(404);
});

it("replaces files", function () {
    $csrf = login($this->app);
    page("builder")->file("notes.txt")->update(["createdAt" => "2026-01-01T10:00:00+00:00", "modifiedAt" => "2026-01-01T10:00:00+00:00"]);
    $uid = page("builder")->file("notes.txt")->uid();
    $replace = fn(string $filename, string $contents) => $this->app->handle(
        (new Nyholm\Psr7\ServerRequest("PUT", "/panel/api/pages/builder/~files/notes.txt", ["X-CSRF-Token" => $csrf], $contents))
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

    [$status, $files] = api($this->app, "PATCH", "/pages/builder/~files", ["sort" => ["photo.png", "notes.txt"]], $csrf);

    expect($status)->toBe(200)
        ->and(array_column($files, "filename"))->toBe(["photo.png", "notes.txt"])
        ->and(page("builder")->file("notes.txt")->data()->sort)->toBe(2)
        ->and(api($this->app, "PATCH", "/pages/builder/~files", ["sort" => "x"], $csrf)[0])->toBe(400);

    [, $files] = api($this->app, "PATCH", "/pages/builder/~files", ["sort" => ["notes.txt"]], $csrf);
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

it("shows the value of an icon field instead of the blueprint's icon", function () {
    file_put_contents("$this->dir/site/blueprints/pages/iconic.yml", "title: Iconic\nicon: page\nfields:\n  icon:\n    type: icon\n");
    $csrf = login($this->app);
    api($this->app, "POST", "/site/~children", ["title" => "Iconic", "type" => "iconic"], $csrf);

    expect(api($this->app, "GET", "/pages/iconic")[1]["icon"])->toBe("page");

    $svg = "data:image/svg+xml;base64," . base64_encode('<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0h1"/></svg>');
    expect(api($this->app, "PATCH", "/pages/iconic", ["data" => ["icon" => "🚀"]], $csrf)[1]["icon"])->toBe("🚀")
        ->and(api($this->app, "PATCH", "/pages/iconic", ["data" => ["icon" => $svg]], $csrf)[1]["icon"])->toBe($svg);

    $tooLarge = "data:image/svg+xml;base64," . base64_encode("<svg>" . str_repeat(" ", 51 * 1024) . "</svg>");
    foreach ([$tooLarge, "data:text/html,<svg>", "data:image/svg+xml,no svg", "a\nb"] as $invalid) {
        expect(api($this->app, "PATCH", "/pages/iconic", ["data" => ["icon" => $invalid]], $csrf)[0])->toBe(422);
    }
});

describe("php files in the content folder", function () {
    beforeEach(function () {
        file_put_contents("$this->dir/content/script.php", "<?php echo 'old';");
        User::create("php-admin@example.com", "secret-password", "admin");
    });

    it("are only editable by the roles and users in content.allowPHP.edit", function () {
        $app = createApp($this->dir, ["content" => ["allowPHP" => ["edit" => ["admin"]]]]);
        $csrf = login($app);
        [, $page] = api($app, "GET", "/pages/script");

        expect($page["php"])->toBe(["file" => "script.php", "canEdit" => false, "code" => null, "hash" => null])
            ->and($page["icon"])->toBe("php")
            ->and(api($app, "PUT", "/pages/script/~php", ["code" => "<?php echo 'new';"], $csrf)[0])->toBe(403);

        $app = createApp($this->dir, ["content" => ["allowPHP" => ["edit" => ["panel@example.com"]]]]);
        $csrf = login($app);
        [, $page] = api($app, "GET", "/pages/script");
        [$status] = api($app, "PUT", "/pages/script/~php", ["code" => "<?php echo 'new';", "hash" => $page["php"]["hash"]], $csrf);

        expect($page["php"]["code"])->toBe("<?php echo 'old';")
            ->and($status)->toBe(200)
            ->and(body($app->request("/script")))->toBe("new");

        $app = createApp($this->dir, ["content" => ["allowPHP" => true]]);
        $csrf = login($app, "php-admin@example.com");

        expect(api($app, "PUT", "/pages/script/~php", ["code" => "<?php"], $csrf)[0])->toBe(403);
    });

    it("checks the syntax before saving, force saves anyway", function () {
        $app = createApp($this->dir, ["content" => ["allowPHP" => ["edit" => ["editor"]]]]);
        $csrf = login($app);
        [$status, $body] = api($app, "PUT", "/pages/script/~php", ["code" => "<?php\necho 'broken'\necho 1;"], $csrf);

        expect($status)->toBe(422)
            ->and($body["syntaxError"]["line"])->toBe(3)
            ->and(file_get_contents("$this->dir/content/script.php"))->toBe("<?php echo 'old';")
            ->and(api($app, "PUT", "/pages/script/~php", ["code" => "<?php\necho 'broken'", "force" => true], $csrf)[0])->toBe(200)
            ->and(file_get_contents("$this->dir/content/script.php"))->toBe("<?php\necho 'broken'");
    });

    it("creates a page.json for the fields of a folder page with only a page.php", function () {
        mkdir("$this->dir/content/releases");
        file_put_contents("$this->dir/content/releases/page.php", "<?php echo 'releases';");
        $app = createApp($this->dir, ["content" => ["allowPHP" => true]]);
        $csrf = login($app);

        // an empty object, an empty array would lose the fields set on it in the panel
        expect(body($app->request("/panel/api/pages/releases")))->toContain('"data":{}')
            ->and(api($app, "PATCH", "/pages/releases", ["data" => ["title" => "Releases"]], $csrf)[0])->toBe(200)
            ->and(json_decode(file_get_contents("$this->dir/content/releases/page.json"), true)["title"])->toBe("Releases")
            ->and(body($app->request("/releases")))->toBe("releases");
    });

    it("rejects changes to a file that has changed in the meantime", function () {
        $app = createApp($this->dir, ["content" => ["allowPHP" => ["edit" => ["editor"]]]]);
        $csrf = login($app);

        expect(api($app, "PUT", "/pages/script/~php", ["code" => "<?php", "hash" => sha1("other")], $csrf)[0])->toBe(409);
    });
});
