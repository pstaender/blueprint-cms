<?php

declare(strict_types=1);

namespace Blueprint;

use Illuminate\Support\Collection;

/**
 * A page is either a folder containing page.{json,md,content.md,php}
 * or a single {slug}.md / {slug}.php file.
 *
 * Folder/file names: `01_blog` → num 1, slug "blog"; a leading `.` marks a draft.
 * php files are ignored unless `content.allowPHP` is enabled.
 */
class Page
{
    use HasFields;

    public const CONTENT_FILES = ["page.json", "page.md", "page.content.md", "page.php"];
    public const FILE_EXTENSIONS = ["md", "php"];
    public const HOME_SLUG = "home";

    private static ?Page $current = null;

    private ?Data $data = null;
    private ?Collection $children = null;
    private ?Blueprint $blueprint = null;

    public function __construct(
        public readonly string $root,
        public readonly string $slug,
        public readonly ?int $num = null,
        public readonly bool $isDraft = false,
        public readonly ?Page $parent = null,
    ) {
    }

    public static function current(): ?self
    {
        return self::$current;
    }

    public static function setCurrent(?self $page): void
    {
        self::$current = $page;
    }

    public static function find(string $path, ?bool $includeDrafts = null): ?self
    {
        return App::instance()->site()->find($path, $includeDrafts);
    }

    /**
     * Creates a page from a folder or file, or returns null if it isn't one.
     */
    public static function fromPath(string $path, ?self $parent = null): ?self
    {
        $name = basename($path);

        if (is_dir($path)) {
            if (!self::folderHasContent($path)) {
                return null;
            }
        } elseif (is_file($path) && in_array(pathinfo($name, PATHINFO_EXTENSION), self::FILE_EXTENSIONS, true)) {
            if (in_array($name, self::CONTENT_FILES, true) || (str_ends_with($name, ".php") && !self::phpFilesEnabled())) {
                return null;
            }
            $name = pathinfo($name, PATHINFO_FILENAME);
        } else {
            return null;
        }

        $isDraft = str_starts_with($name, ".");
        $name = ltrim($name, ".");

        if (!preg_match('/^(?:(\d+)_)?(.+)$/', $name, $matches)) {
            return null;
        }

        return new self(
            root: $path,
            slug: $matches[2],
            num: $matches[1] === "" ? null : (int) $matches[1],
            isDraft: $isDraft,
            parent: $parent,
        );
    }

    /**
     * All pages (including drafts) within a folder, sorted by num and slug.
     * Folder pages win over file pages with the same slug, published over drafts.
     */
    public static function scan(string $dir, ?self $parent = null): Collection
    {
        if (!is_dir($dir)) {
            return new Collection();
        }

        return (new Collection(scandir($dir)))
            // the folders of the records' trash and files are never pages (a leading `.` would be a draft)
            ->reject(fn(string $name) => in_array($name, [".", "..", Model::TRASH_FOLDER, Model::FILES_FOLDER], true))
            ->map(fn(string $name) => self::fromPath("$dir/$name", $parent))
            ->filter()
            ->sortBy(fn(self $page) => [$page->isDraft, !$page->isFolder()])
            ->unique(fn(self $page) => $page->slug)
            ->sortBy([
                fn(self $a, self $b) => ($a->num ?? PHP_INT_MAX) <=> ($b->num ?? PHP_INT_MAX),
                fn(self $a, self $b) => $a->slug <=> $b->slug,
            ])
            ->values();
    }

    private static function folderHasContent(string $dir): bool
    {
        foreach (self::CONTENT_FILES as $file) {
            if (is_file("$dir/$file") && ($file !== "page.php" || self::phpFilesEnabled())) {
                return true;
            }
        }

        return false;
    }

    public function isFolder(): bool
    {
        return is_dir($this->root);
    }

    public function isHome(): bool
    {
        return $this->parent === null && $this->slug === self::HOME_SLUG;
    }

    /**
     * The slug path, e.g. "blog/newest_entry".
     */
    public function id(): string
    {
        return ltrim(($this->parent?->id() ?? "") . "/" . $this->slug, "/");
    }

    /**
     * Pages with (published) children end with a slash, e.g. "/blog/".
     */
    public function url(): string
    {
        if ($this->isHome()) {
            return "/";
        }

        return "/" . $this->id() . ($this->hasChildren() ? "/" : "");
    }

    public function data(): Data
    {
        return $this->data ??= new Data($this->readData());
    }

    public function mergeData(array $values): void
    {
        $this->data = $this->data()->merge($values);
    }

    private function readData(): array
    {
        if (!$this->isFolder()) {
            return str_ends_with($this->root, ".md") ? SystemFields::fileDates(ContentFile::read($this->root), $this->root) : [];
        }

        $data = ContentFile::read("$this->root/page.json");

        if (is_file("$this->root/page.md")) {
            $data = array_replace($data, ContentFile::read("$this->root/page.md"));
        }

        if (is_file("$this->root/page.content.md")) {
            $data["content"] = trim(file_get_contents("$this->root/page.content.md"));
        }

        return $this->contentFormat() === "md" ? SystemFields::fileDates($data, $this->dataFile()) : $data;
    }

    /**
     * The file that the fields are written to.
     */
    private function dataFile(): string
    {
        return match (true) {
            !$this->isFolder() => $this->root,
            is_file("$this->root/page.md") => "$this->root/page.md",
            default => "$this->root/page.json",
        };
    }

    /**
     * `content.allowPHP` is `true` or an array (with the `edit` permissions).
     */
    public static function phpFilesEnabled(): bool
    {
        $value = App::instance()->config->get("content.allowPHP");

        return $value === true || is_array($value);
    }

    /**
     * A page.php (or {slug}.php) renders the page itself instead of a template.
     */
    public function phpFile(): ?string
    {
        $file = $this->isFolder() ? "$this->root/page.php" : $this->root;

        return self::phpFilesEnabled() && str_ends_with($file, ".php") && is_file($file) ? $file : null;
    }

    /**
     * Overwrites the page's php file, e.g. from the panel. Check the code with phpSyntaxError() before.
     */
    public function updatePhp(string $code): void
    {
        $file = $this->phpFile() ?? throw new \LogicException("The page \"{$this->id()}\" has no php file");

        if (file_put_contents($file, $code, LOCK_EX) === false) {
            throw new \RuntimeException("Can't write $file");
        }

        if (function_exists("opcache_invalidate")) {
            opcache_invalidate($file, true);
        }
    }

    /**
     * Parses the code without executing it (like `php -l`), returns the syntax error or null.
     */
    public static function phpSyntaxError(string $code): ?\ParseError
    {
        try {
            token_get_all($code, TOKEN_PARSE);

            return null;
        } catch (\ParseError $error) {
            return $error;
        }
    }

    public const DEFAULT_TYPE = "page";

    /**
     * How the data of a page is stored: `json` (page.json), `md` (page.md: yaml front matter + text)
     * or `json+md` (page.json + the text in page.content.md).
     */
    public const CONTENT_FORMATS = ["json", "md", "json+md"];

    /**
     * The page type (`type` in the data), chooses the blueprint (pages/{type}.yml)
     * and the template, unless `template` is set.
     */
    public function type(): string
    {
        $type = $this->data()->type;

        return is_string($type) && preg_match('/^[\w-]+$/', $type) ? $type : self::DEFAULT_TYPE;
    }

    /**
     * `template` in the data, falls back to the type (templates/{template}.php → page.php → default.php).
     */
    public function template(): string
    {
        $template = $this->data()->template;

        return is_string($template) && preg_match('/^[\w-]+$/', $template) ? $template : $this->type();
    }

    public function changeType(string $type): void
    {
        if (!preg_match('/^[\w-]+$/', $type)) {
            throw new \InvalidArgumentException("Invalid page type \"$type\"");
        }

        $this->update(["type" => $type === self::DEFAULT_TYPE ? null : $type]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, File>
     */
    public function files(): Collection
    {
        return new Collection(File::scan($this));
    }

    public function file(string $filename): ?File
    {
        return $this->files()->first(fn(File $file) => $file->filename() === $filename);
    }

    /**
     * Stores the order as `sort` in the files' data, files missing in the list keep their order after them.
     *
     * @param string[] $filenames
     */
    public function sortFiles(array $filenames): void
    {
        $files = $this->files()->keyBy(fn(File $file) => $file->filename());
        $sorted = $files->only($filenames)->sortBy(fn(File $file) => array_search($file->filename(), $filenames, true));

        foreach ($sorted->merge($files->except($filenames))->values() as $index => $file) {
            if ($file->sort() !== $index + 1) {
                $file->update(["sort" => $index + 1]);
            }
        }
    }

    /**
     * See Blueprint::forPage() for how the blueprint is chosen.
     */
    public function blueprint(): Blueprint
    {
        return $this->blueprint ??= Blueprint::forPage($this);
    }

    /**
     * The `title` field, falls back to the slug.
     */
    public function title(): FieldValue
    {
        return $this->field("title")->or($this->slug);
    }

    protected function fieldDefinition(string $name): ?Field
    {
        return $this->blueprint()->field($name);
    }

    /**
     * All children including drafts.
     */
    public function allChildren(): Collection
    {
        return $this->children ??= $this->isFolder() ? self::scan($this->root, $this) : new Collection();
    }

    public function children(): Collection
    {
        return $this->allChildren()->reject(fn(self $page) => $page->isDraft)->values();
    }

    public function drafts(): Collection
    {
        return $this->allChildren()->filter(fn(self $page) => $page->isDraft)->values();
    }

    public function hasChildren(): bool
    {
        return $this->children()->isNotEmpty();
    }

    public function child(string $slug, bool $includeDrafts = false): ?self
    {
        return $this->allChildren()->first(
            fn(self $page) => $page->slug === $slug && ($includeDrafts || !$page->isDraft)
        );
    }

    /**
     * Listed pages are the ones with a number prefix, e.g. for navigations.
     */
    public function isListed(): bool
    {
        return $this->num !== null;
    }

    public function uid(): ?string
    {
        $uid = $this->data()->uid;

        return is_string($uid) && $uid !== "" ? $uid : null;
    }

    /**
     * Renames the slug and keeps the status (draft prefix, number).
     */
    public function changeSlug(string $slug): self
    {
        self::validateSlug($slug);

        if ($this->isHome()) {
            throw new \LogicException("The slug of the home page can't be changed");
        }

        if ($slug === $this->slug) {
            return $this;
        }

        if ($this->siblings()->contains(fn(self $page) => $page->slug === $slug)) {
            throw new \InvalidArgumentException("A page with the slug \"$slug\" already exists");
        }

        $prefix = $this->isDraft ? "." : ($this->isListed() ? sprintf("%02d_", $this->num) : "");

        return $this->moveTo($prefix . $slug);
    }

    private static function validateSlug(string $slug): void
    {
        if (!preg_match('/^[a-z0-9][a-z0-9_-]*$/', $slug)) {
            throw new \InvalidArgumentException("The slug may only contain a-z, 0-9, - and _");
        }
    }

    public function contentFormat(): string
    {
        return match (true) {
            !$this->isFolder() => "md",
            is_file("$this->root/page.content.md") => "json+md",
            is_file("$this->root/page.md") => "md",
            default => "json",
        };
    }

    /**
     * Rewrites the data in the given format and removes the other content files. A single file page
     * (`about.md`) becomes a folder (`about/`) for the json formats.
     */
    public function changeContentFormat(string $format): self
    {
        if (!in_array($format, self::CONTENT_FORMATS, true)) {
            throw new \InvalidArgumentException("Invalid content format \"$format\" (" . implode(", ", self::CONTENT_FORMATS) . ")");
        }

        if (!$this->isEditable()) {
            throw new \LogicException("The page \"{$this->id()}\" is a php file and can't be converted");
        }

        if ($format === $this->contentFormat()) {
            return $this;
        }

        $data = SystemFields::apply($this->readData(), $this->readData());
        $dir = $this->isFolder() ? $this->root : substr($this->root, 0, -strlen(".md"));

        if (!$this->isFolder()) {
            if (file_exists($dir)) {
                throw new \RuntimeException("Can't convert the page, $dir already exists");
            }

            mkdir($dir, 0775);
        }

        // the new files are written first, the old ones are only removed afterwards
        $content = trim((string) ($data["content"] ?? ""));
        $files = match ($format) {
            "json" => ["page.json" => ContentFile::toJson($data)],
            "md" => ["page.md" => ContentFile::toMarkdown(SystemFields::forMarkdown($data))],
            "json+md" => [
                "page.json" => ContentFile::toJson(array_diff_key($data, ["content" => true])),
                "page.content.md" => "$content\n",
            ],
        };

        foreach ($files as $name => $output) {
            if (file_put_contents("$dir/$name", $output, LOCK_EX) === false) {
                throw new \RuntimeException("Can't write $dir/$name");
            }
        }

        foreach (array_diff(["page.json", "page.md", "page.content.md"], array_keys($files)) as $name) {
            if (is_file("$dir/$name")) {
                unlink("$dir/$name");
            }
        }

        if (!$this->isFolder()) {
            unlink($this->root);
        }

        App::instance()->refreshSite();

        return self::fromPath($dir, $this->parent) ?? throw new \RuntimeException("Can't read the converted page $dir");
    }

    public function status(): string
    {
        return $this->isDraft ? "draft" : ($this->isListed() ? "listed" : "unlisted");
    }

    /**
     * Pages rendered by a {slug}.php file have no data file to write to.
     */
    public function isEditable(): bool
    {
        return $this->isFolder() || str_ends_with($this->root, ".md");
    }

    /**
     * Saves the data to the page's content files, `null` values are removed.
     */
    public function update(array $values): void
    {
        if (!$this->isEditable()) {
            throw new \LogicException("The page \"{$this->id()}\" is a php file and can't be edited");
        }

        $existing = $this->readData();
        $data = array_filter([...$existing, ...$values], fn($value) => $value !== null);
        $file = $this->dataFile();
        $data = str_ends_with($file, ".md")
            ? SystemFields::forMarkdown($data, ContentFile::read($file))
            : SystemFields::apply($data, $existing);

        if (is_file("$this->root/page.content.md")) {
            file_put_contents("$this->root/page.content.md", trim((string) ($data["content"] ?? "")) . "\n", LOCK_EX);
            unset($data["content"]);
        }

        ContentFile::write($file, $data);

        $this->data = null;
    }

    /**
     * Renames the folder (or file): `.slug` (draft), `slug` (unlisted), `NN_slug` (listed).
     * Listed pages keep their number, otherwise they are appended to the listed siblings.
     */
    public function changeStatus(string $status): self
    {
        if ($status === "draft" && $this->isHome()) {
            throw new \LogicException("The home page can't be a draft");
        }

        $name = match ($status) {
            "draft" => ".$this->slug",
            "unlisted" => $this->slug,
            "listed" => sprintf("%02d_%s", $this->isListed() ? $this->num : $this->nextNum(), $this->slug),
            default => throw new \InvalidArgumentException("Invalid status \"$status\""),
        };

        return $this->moveTo($name);
    }

    /**
     * Moves the page with its subpages and files below another folder page (null = root). It keeps its status,
     * listed pages are appended to the listed pages of the new parent.
     */
    public function changeParent(?self $parent): self
    {
        if ($this->isHome()) {
            throw new \LogicException("The home page can't be moved");
        }

        if ($parent?->root === $this->parent?->root) {
            return $this;
        }

        if ($parent !== null && !$parent->isFolder()) {
            throw new \LogicException("Pages can only be moved into folder pages");
        }

        if ($parent !== null && str_starts_with("$parent->root/", "$this->root/")) {
            throw new \LogicException("A page can't be moved into itself or its subpages");
        }

        $siblings = $parent?->allChildren() ?? App::instance()->site()->allChildren();

        if ($siblings->contains(fn(self $page) => $page->slug === $this->slug)) {
            throw new \InvalidArgumentException("A page with the slug \"$this->slug\" already exists there");
        }

        $num = (int) $siblings->max(fn(self $page) => $page->num ?? 0) + 1;
        $name = match ($this->status()) {
            "draft" => ".$this->slug",
            "unlisted" => $this->slug,
            "listed" => sprintf("%02d_%s", $num, $this->slug),
        };

        return $this->moveInto($parent?->root ?? App::instance()->contentDir(), $name, $parent);
    }

    /**
     * Moves a listed page to the given position among its listed siblings and renumbers them.
     */
    public function changeNum(int $position): self
    {
        $siblings = $this->siblings()->filter(fn(self $page) => $page->isListed() && $page->root !== $this->root)->values();
        $position = max(1, min($position, $siblings->count() + 1));
        $siblings->splice($position - 1, 0, [$this]);

        $moved = $this;

        foreach ($siblings->values() as $index => $page) {
            $renamed = $page->num === $index + 1 ? $page : $page->moveTo(sprintf("%02d_%s", $index + 1, $page->slug));
            $moved = $page === $this ? $renamed : $moved;
        }

        return $moved;
    }

    /**
     * Creates a draft below the given parent (null = root), e.g. `content/01_blog/.new-article/page.json`.
     */
    public static function create(?self $parent, string $slug, array $data): self
    {
        self::validateSlug($slug);

        $dir = $parent === null ? App::instance()->contentDir() : $parent->root;

        if ($parent !== null && !$parent->isFolder()) {
            throw new \LogicException("Subpages can only be created in folder pages");
        }

        $siblings = $parent === null ? App::instance()->site()->allChildren() : $parent->allChildren();

        if ($siblings->contains(fn(self $page) => $page->slug === $slug)) {
            throw new \InvalidArgumentException("A page with the slug \"$slug\" already exists");
        }

        ContentFile::write("$dir/.$slug/page.json", SystemFields::apply(array_diff_key($data, array_flip(SystemFields::NAMES))));
        App::instance()->refreshSite();

        return self::fromPath("$dir/.$slug", $parent);
    }

    public function delete(): void
    {
        if ($this->isHome()) {
            throw new \LogicException("The home page can't be deleted");
        }

        self::deletePath($this->root);
        App::instance()->refreshSite();
    }

    public function siblings(): Collection
    {
        return $this->parent?->allChildren() ?? App::instance()->site()->allChildren();
    }

    private function nextNum(): int
    {
        return (int) $this->siblings()->max(fn(self $page) => $page->num ?? 0) + 1;
    }

    private function moveTo(string $name): self
    {
        return $this->moveInto(dirname($this->root), $name, $this->parent);
    }

    private function moveInto(string $dir, string $name, ?self $parent): self
    {
        $extension = $this->isFolder() ? "" : "." . pathinfo($this->root, PATHINFO_EXTENSION);
        $target = "$dir/$name$extension";

        if ($target === $this->root) {
            return $this;
        }

        if (file_exists($target)) {
            throw new \RuntimeException("Can't rename the page, $target already exists");
        }

        rename($this->root, $target);
        App::instance()->refreshSite();

        return self::fromPath($target, $parent);
    }

    private static function deletePath(string $path): void
    {
        if (is_dir($path) && !is_link($path)) {
            foreach (array_diff(scandir($path), [".", ".."]) as $name) {
                self::deletePath("$path/$name");
            }
            rmdir($path);
        } else {
            unlink($path);
        }
    }
}
