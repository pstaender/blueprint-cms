<?php

use Blueprint\Blueprint;
use Blueprint\Cli;

describe("plugins", function () {
    beforeEach(fn() => $this->app = createApp(WEBSITE_PROJECT));

    it("loads the plugins with an index.php or a php file named like the folder", function () {
        expect(array_keys($this->app->plugins()))->toBe(["example", "second-example"])
            ->and(function_exists("example_plugin_greeting"))->toBeTrue()
            ->and(defined("SECOND_EXAMPLE_PLUGIN_LOADED"))->toBeTrue();
    });

    it("uses the templates, snippets, partials and blueprints of plugins, the site wins", function () {
        expect($this->app->renderTemplate("plugin"))->toContain("Hello from the example plugin", "plugin snippet")
            ->and($this->app->templateFile("no-entry"))->toEndWith("site/templates/default.php")
            ->and($this->app->renderPartial("greeting", ["name" => "Ada", "page" => $this->app->site()->find("about")]))->toContain("Hello Ada")
            ->and($this->app->renderPartial("plugin-partial"))->toBe("<em>plugin partial</em>\n")
            ->and(Blueprint::load("pages/plugin")->title())->toBe("Plugin page")
            ->and(Blueprint::forType("plugin")->name)->toBe("pages/plugin");
    });

    it("registers the commands of plugins, the site wins", function () {
        $commands = Cli::commands();

        expect($commands["plugin:hello"]["userDefined"])->toBeTrue()
            ->and($commands["greet"]["description"])->toBe("greets someone");
    });
});

function buildStaticSite(array $args): array
{
    ob_start();
    $code = Cli::run(["bin/blueprint", "static:build", ...$args]);

    return [$code, ob_get_clean()];
}

describe("static site generator", function () {
    beforeEach(function () {
        $this->dir = copyProject(WEBSITE_PROJECT);
        // the fixture plugins are already loaded from the original fixture
        exec("rm -rf " . escapeshellarg("$this->dir/site/plugins") . " && mkdir -p " . escapeshellarg("$this->dir/site/plugins"));
        // linked, so its class is loaded once for all tests
        symlink(dirname(__DIR__) . "/site/plugins/static-site-generator", "$this->dir/site/plugins/static-site-generator");

        $entry = "$this->dir/content/01_blog/01_newest_entry";
        file_put_contents("$entry/used.txt", "used");
        file_put_contents("$entry/unused.txt", "unused");
        file_put_contents("$entry/page.json", json_encode([...json_decode(file_get_contents("$entry/page.json"), true), "attachment" => "used.txt"]));
        mkdir("$this->dir/public/panel", 0777, true);
        file_put_contents("$this->dir/public/index.php", "<?php");
        file_put_contents("$this->dir/public/panel/index.html", "panel");
        file_put_contents("$this->dir/public/style.css", "body {}");

        $this->app = createApp($this->dir);
        $this->target = "$this->dir/static";
    });

    afterEach(fn() => removeProject($this->dir));

    it("renders the listed and unlisted pages, used files, redirects and public files", function () {
        [$code, $output] = buildStaticSite([$this->target]);

        expect($code)->toBe(0)
            ->and($output)->toContain("Wrote 6 pages, 1 files and 1 redirects")
            ->and(file_get_contents("$this->target/index.html"))->toBe(body($this->app->request("/")))
            ->and(file_get_contents("$this->target/blog/newest_entry/index.html"))->toBe(body($this->app->request("/blog/newest_entry")))
            ->and("$this->target/blog/index.html")->toBeFile()
            ->and("$this->target/about/index.html")->toBeFile()
            ->and("$this->target/imprint/index.html")->toBeFile()
            ->and("$this->target/this_is_a_draft_page")->not->toBeDirectory()
            ->and("$this->target/404.html")->toBeFile()
            ->and(file_get_contents("$this->target/blog/newest_entry/used.txt"))->toBe("used")
            ->and("$this->target/blog/newest_entry/unused.txt")->not->toBeFile()
            ->and(file_get_contents("$this->target/from-foo/index.html"))->toContain('url=/to-bar"')
            ->and(file_get_contents("$this->target/style.css"))->toBe("body {}")
            ->and("$this->target/index.php")->not->toBeFile()
            ->and("$this->target/panel")->not->toBeDirectory();
    });

    it("doesn't replace an existing folder without --clear-existing-target-folder", function () {
        mkdir($this->target);
        file_put_contents("$this->target/old.html", "old");

        expect(buildStaticSite([$this->target])[0])->toBe(1)
            ->and("$this->target/old.html")->toBeFile()
            ->and(buildStaticSite([$this->target, "--clear-existing-target-folder"])[0])->toBe(0)
            ->and("$this->target/old.html")->not->toBeFile()
            ->and("$this->target/index.html")->toBeFile();
    });

    it("refuses project folders as target", function () {
        foreach ([$this->dir, dirname($this->dir), "$this->dir/content", "$this->dir/public/static", "$this->dir/site", "$this->dir/static/../content"] as $target) {
            expect(buildStaticSite([$target, "--clear-existing-target-folder"])[0])->toBe(1);
        }

        expect("$this->dir/content/site.json")->toBeFile();
    });
});
