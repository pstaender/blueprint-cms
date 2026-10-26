<?php

use Blueprint\Cli;
use Blueprint\PublicFolder;

beforeEach(function () {
    $this->source = copyProject(__DIR__ . "/fixtures/website") . "/package-public";
    mkdir("$this->source/panel/assets", 0755, true);
    file_put_contents("$this->source/index.php", "<?php // front controller");
    file_put_contents("$this->source/.htaccess", "RewriteEngine On");
    file_put_contents("$this->source/panel/index.html", "<!doctype html>");
    file_put_contents("$this->source/panel/assets/index-new.js", "new");
    $this->target = dirname($this->source) . "/public";
});

afterEach(fn() => removeProject(dirname($this->source)));

it("copies the front controller, the .htaccess and the panel", function () {
    (new PublicFolder($this->source))->copyTo($this->target);

    expect(file_get_contents("$this->target/index.php"))->toBe("<?php // front controller")
        ->and(file_get_contents("$this->target/.htaccess"))->toBe("RewriteEngine On")
        ->and(file_get_contents("$this->target/panel/assets/index-new.js"))->toBe("new");
});

it("keeps an existing front controller unless forced and replaces the panel", function () {
    mkdir("$this->target/panel/assets", 0755, true);
    file_put_contents("$this->target/index.php", "custom");
    file_put_contents("$this->target/panel/assets/index-old.js", "old");

    $done = (new PublicFolder($this->source))->copyTo($this->target);

    expect(file_get_contents("$this->target/index.php"))->toBe("custom")
        ->and($done[0])->toStartWith("Kept")
        ->and("$this->target/panel/assets/index-old.js")->not->toBeFile()
        ->and("$this->target/panel/assets/index-new.js")->toBeFile();

    (new PublicFolder($this->source))->copyTo($this->target, force: true);

    expect(file_get_contents("$this->target/index.php"))->toBe("<?php // front controller");
});

it("refuses to copy an unbuilt panel", function () {
    unlink("$this->source/panel/index.html");

    expect(fn() => (new PublicFolder($this->source))->copyTo($this->target))->toThrow(RuntimeException::class, "panel:build");
});

it("doesn't copy the package's public folder onto itself", function () {
    expect((new PublicFolder($this->source))->copyTo($this->source)[0])->toContain("nothing to copy");
});

it("registers public:build", function () {
    createApp(WEBSITE_PROJECT);

    expect(array_keys(Cli::commands()))->toContain("public:build");
});
