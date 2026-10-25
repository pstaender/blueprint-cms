<?php

declare(strict_types=1);

namespace Blueprint;

use Illuminate\Support\Collection;

/**
 * A page is either a folder containing page.{json,md,content.md,php}
 * or a single {slug}.md / {slug}.php file.
 *
 * Folder/file names: `01_blog` → num 1, slug "blog"; a leading `.` marks a draft.
 */
class Page
{
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
            if (in_array($name, self::CONTENT_FILES, true)) {
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
            ->reject(fn(string $name) => $name === "." || $name === "..")
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
            if (is_file("$dir/$file")) {
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
            return ContentFile::read($this->root);
        }

        $data = ContentFile::read("$this->root/page.json");

        if (is_file("$this->root/page.md")) {
            $data = array_replace($data, ContentFile::read("$this->root/page.md"));
        }

        if (is_file("$this->root/page.content.md")) {
            $data["content"] = trim(file_get_contents("$this->root/page.content.md"));
        }

        return $data;
    }

    /**
     * A page.php (or {slug}.php) renders the page itself instead of a template.
     */
    public function phpFile(): ?string
    {
        $file = $this->isFolder() ? "$this->root/page.php" : $this->root;

        return str_ends_with($file, ".php") && is_file($file) ? $file : null;
    }

    public function template(): string
    {
        return (string) ($this->data()->template ?? "default");
    }

    /**
     * See Blueprint::forPage() for how the blueprint is chosen.
     */
    public function blueprint(): Blueprint
    {
        return $this->blueprint ??= Blueprint::forPage($this);
    }

    public function title(): string
    {
        return (string) ($this->data()->title ?? $this->slug);
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
}
