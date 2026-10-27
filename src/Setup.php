<?php

declare(strict_types=1);

namespace Blueprint;

use Ahc\Cli\IO\Interactor;

/**
 * The interactive setup of a website (`bin/blueprint setup`): public folder, users, .gitignore, composer scripts
 * and the most important config options. Every step can be run on its own.
 */
class Setup
{
    public const GITIGNORE_TEMPLATE = __DIR__ . "/../.gitignore_template";
    public const COMPOSER_SCRIPT = "blueprint public:setup";

    public function __construct(
        private readonly Interactor $io,
        private readonly string $projectDir,
    ) {
    }

    /** Without a terminal (piped or no input), every question would be answered with its default. */
    public static function inTerminal(): self
    {
        if (!stream_isatty(STDIN)) {
            throw new \RuntimeException("The setup asks questions, run it in a terminal");
        }

        return new self(new Interactor(), App::instance()->projectDir());
    }

    public function run(): void
    {
        $this->step("Public folder");
        $this->publicFolder();
        $this->step("Users");
        $this->users();
        $this->step(".gitignore");
        $this->gitignore();
        $this->step("composer.json");
        $this->composer();
        $this->step("Config");
        $this->config();
        $this->io->eol()->ok("Setup finished, start the dev server with: php -S localhost:8000 -t public public/index.php", true);
    }

    public function publicFolder(bool $force = false): void
    {
        foreach ((new PublicFolder())->copyTo(App::instance()->publicDir(), $force) as $line) {
            $this->io->write($line, true);
        }
    }

    public function users(): void
    {
        if (User::all()->isEmpty() && $this->io->confirm("There are no users yet. Create an admin user?", "y")) {
            $this->io->comment("This user has admin rights: they can create, edit and delete all users and change their roles.", true);
            $this->createUser(User::ADMIN_ROLE, "admin");
        }

        while ($this->io->confirm("Create another (non-admin) user?", "n")) {
            $roles = array_values(array_diff(User::roles(), [User::ADMIN_ROLE]));
            $role = count($roles) > 1 ? $this->io->choice("Role", $roles, User::DEFAULT_ROLE) : User::DEFAULT_ROLE;
            $this->createUser($role);
        }
    }

    private function createUser(string $role, ?string $suggestion = null): void
    {
        $email = $this->io->prompt("Email or name", $suggestion, function (string $email) {
            if (trim($email) === "" || User::find($email) !== null) {
                throw new \InvalidArgumentException("The email is empty or already taken");
            }

            return trim($email);
        });
        $password = $this->io->promptHidden("Password (min. 8 characters)", function (string $password) {
            if (mb_strlen($password) < 8) {
                throw new \InvalidArgumentException("The password needs at least 8 characters");
            }

            return $password;
        });

        $user = User::create($email, $password, $role);
        $this->io->ok("Created {$user->email()} ({$user->role()})", true);
    }

    public function gitignore(): void
    {
        $file = "$this->projectDir/.gitignore";
        $existing = is_file($file) ? file_get_contents($file) : "";
        $missing = self::missingGitignoreLines($existing, file_get_contents(self::GITIGNORE_TEMPLATE));

        if ($missing === []) {
            $this->io->write(".gitignore is complete", true);

            return;
        }

        $this->io->write(is_file($file) ? ".gitignore misses these lines:" : "There is no .gitignore, it would contain:", true);
        $this->io->comment("  " . implode(PHP_EOL . "  ", $missing), true);

        if ($this->io->confirm("Add them?", "y")) {
            $separator = $existing === "" || str_ends_with($existing, "\n") ? "" : PHP_EOL;
            file_put_contents($file, $existing . $separator . implode(PHP_EOL, $missing) . PHP_EOL);
            $this->io->ok("Updated .gitignore", true);
        }
    }

    /**
     * The lines of the template that the .gitignore doesn't have yet, with the comments directly above them.
     *
     * @return string[]
     */
    public static function missingGitignoreLines(string $gitignore, string $template): array
    {
        $existing = array_map("trim", explode("\n", $gitignore));
        $missing = [];
        $comments = [];

        foreach (explode("\n", $template) as $line) {
            $line = trim($line);
            if (str_starts_with($line, "#")) {
                $comments[] = $line;
                continue;
            }
            if ($line !== "" && !in_array($line, $existing, true)) {
                $missing = [...$missing, ...$comments, $line];
            }
            $comments = [];
        }

        return $missing;
    }

    public function composer(): void
    {
        $file = "$this->projectDir/composer.json";
        if (!is_file($file)) {
            $this->io->write("There is no composer.json", true);

            return;
        }

        $composer = json_decode(file_get_contents($file), flags: JSON_THROW_ON_ERROR);
        $updated = self::withComposerScripts($composer);

        if ($updated == $composer) {
            $this->io->write("composer.json already runs public:setup after install and update", true);

            return;
        }

        $this->io->write("Composer can copy the panel into public/ after every install and update of Blueprint CMS:", true);
        $this->io->comment('  "post-install-cmd": "' . self::COMPOSER_SCRIPT . '"' . PHP_EOL . '  "post-update-cmd": "' . self::COMPOSER_SCRIPT . '"', true);

        if ($this->io->confirm("Add these scripts to composer.json?", "y")) {
            file_put_contents($file, json_encode($updated, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
            $this->io->ok("Updated composer.json", true);
        }
    }

    /**
     * Adds `blueprint public:setup` to the post-install-cmd and post-update-cmd scripts (decoded composer.json
     * as objects, so empty objects stay objects), existing scripts are kept.
     */
    public static function withComposerScripts(\stdClass $composer): \stdClass
    {
        $composer = json_decode(json_encode($composer));
        $composer->scripts ??= new \stdClass();

        foreach (["post-install-cmd", "post-update-cmd"] as $event) {
            $scripts = (array) ($composer->scripts->$event ?? []);
            if (!in_array(self::COMPOSER_SCRIPT, $scripts, true)) {
                $scripts[] = self::COMPOSER_SCRIPT;
                $composer->scripts->$event = count($scripts) === 1 ? $scripts[0] : $scripts;
            }
        }

        return $composer;
    }

    public function config(): void
    {
        $file = "$this->projectDir/config/config.php";
        $current = Config::readFolder(dirname($file), "");
        $content = [];

        if (isset($current["content"]["optimizeImages"])) {
            $this->io->write("content.optimizeImages is already configured", true);
        } else {
            $content["optimizeImages"] = $this->io->confirm("Downsize large images automatically for visitors (AVIF)?", "y");
            if ($content["optimizeImages"] && !extension_loaded("gd") && !extension_loaded("imagick")) {
                $this->io->warn("This needs the gd or imagick extension of PHP", true);
            }
        }

        if (isset($current["content"]["allowPHP"])) {
            $this->io->write("content.allowPHP is already configured", true);
        } elseif ($this->io->confirm("Allow php files in the content folder as pages?", "y")) {
            $editors = $this->io->choice("Who may edit them in the panel?", [
                "none" => "nobody, only in the file system",
                "admins" => "admins",
                "anyone" => "every panel user (the roles that exist now)",
            ], "admins");
            $content["allowPHP"] = match ($editors) {
                "admins" => ["edit" => [User::ADMIN_ROLE]],
                "anyone" => ["edit" => User::roles()],
                default => true,
            };
        } else {
            $content["allowPHP"] = false;
        }

        if ($content === []) {
            return;
        }

        if (is_file($file)) {
            $this->io->write("Add this to the array in config/config.php:", true);
            $this->io->comment(self::exportPhp(["content" => $content]), true);

            return;
        }

        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0755, true);
        }
        file_put_contents($file, "<?php\n\nreturn " . self::exportPhp(["content" => $content]) . ";\n");
        $this->io->ok("Created config/config.php", true);
    }

    /** Short array syntax with double quotes, like the config files of the docs. */
    public static function exportPhp(mixed $value, int $level = 0): string
    {
        if (!is_array($value)) {
            return is_string($value) ? json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : var_export($value, true);
        }

        if (array_is_list($value)) {
            return "[" . implode(", ", array_map(fn($item) => self::exportPhp($item, $level), $value)) . "]";
        }

        $indent = str_repeat("    ", $level + 1);
        $lines = array_map(
            fn($key, $item) => $indent . self::exportPhp($key) . " => " . self::exportPhp($item, $level + 1) . ",",
            array_keys($value),
            $value,
        );

        return "[\n" . implode("\n", $lines) . "\n" . str_repeat("    ", $level) . "]";
    }

    private function step(string $title): void
    {
        $this->io->eol()->boldGreen($title, true);
    }
}
