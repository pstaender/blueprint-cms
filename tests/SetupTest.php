<?php

use Ahc\Cli\IO\Interactor;
use Blueprint\Cli;
use Blueprint\Setup;
use Blueprint\User;

// no stty calls for hidden prompts
defined("RUNNING_TEST") || define("RUNNING_TEST", true);

beforeEach(function () {
    $this->project = copyProject(BLUEPRINT_PROJECT);
    createApp($this->project);
    $this->setup = function (string $answers) {
        file_put_contents("$this->project/answers", $answers);

        return new Setup(new Interactor("$this->project/answers", "$this->project/output"), $this->project);
    };
});

afterEach(fn() => removeProject($this->project));

it("registers the setup commands", function () {
    expect(array_keys(Cli::commands()))->toContain("setup", "setup:gitignore", "setup:composer", "repl", "public:setup");
});

it("finds the missing lines of the .gitignore with their comments", function () {
    $template = "# composer packages\n/vendor\n# nodejs modules\nnode_modules/\n\n# media\n/public/media/\n";

    expect(Setup::missingGitignoreLines("/vendor\n.DS_Store\n", $template))
        ->toBe(["# nodejs modules", "node_modules/", "# media", "/public/media/"])
        ->and(Setup::missingGitignoreLines("/vendor\nnode_modules/\n/public/media/", $template))->toBe([]);
});

it("adds the missing lines to the .gitignore", function () {
    file_put_contents("$this->project/.gitignore", "/vendor");

    ($this->setup)("y\n")->gitignore();

    $lines = explode("\n", file_get_contents("$this->project/.gitignore"));
    expect($lines[0])->toBe("/vendor")
        ->and($lines)->toContain("/content/users.json", "# contains panel users credentials")
        ->and(array_count_values($lines)["/vendor"])->toBe(1);
});

it("adds public:setup to the composer scripts and keeps other scripts", function () {
    file_put_contents("$this->project/composer.json", json_encode([
        "require" => new stdClass(),
        "scripts" => ["post-update-cmd" => "echo updated"],
    ]));

    ($this->setup)("y\n")->composer();

    $composer = file_get_contents("$this->project/composer.json");
    expect(json_decode($composer, true)["scripts"])->toBe([
        "post-update-cmd" => ["echo updated", "blueprint public:setup"],
        "post-install-cmd" => "blueprint public:setup",
    ])->and($composer)->toContain('"require": {}');

    $updated = Setup::withComposerScripts(json_decode($composer));
    expect($updated)->toEqual(json_decode($composer));
});

it("creates an admin and other users", function () {
    unlink("$this->project/content/users.json");

    ($this->setup)("y\n\nshort\nadmin-password\ny\n\neditor@example.com\neditor-password\nn\n")->users();

    expect(User::find("admin")->role())->toBe("admin")
        ->and(User::find("admin")->verifyPassword("admin-password"))->toBeTrue()
        ->and(User::find("editor@example.com")->role())->toBe("user")
        ->and(file_get_contents("$this->project/output"))->toContain("admin rights", "at least 8 characters");
});

it("doesn't ask for an admin if there are users", function () {
    $count = User::all()->count();

    ($this->setup)("\n")->users();

    expect(User::all()->count())->toBe($count);
});

it("writes the config", function () {
    ($this->setup)("y\ny\nadmins\n")->config();

    $config = require "$this->project/config/config.php";
    expect($config)->toBe(["content" => ["optimizeImages" => true, "allowPHP" => ["edit" => ["admin"]]]]);
});

it("only asks for options that aren't configured and doesn't overwrite a config file", function () {
    mkdir("$this->project/config");
    file_put_contents("$this->project/config/config.php", '<?php return ["content" => ["optimizeImages" => false]];');

    ($this->setup)("n\n")->config();

    expect(file_get_contents("$this->project/config/config.php"))->toContain("optimizeImages\" => false")
        ->and(file_get_contents("$this->project/output"))->toContain("already configured", '"allowPHP" => false');
});
