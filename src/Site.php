<?php

declare(strict_types=1);

namespace Blueprint;

use Illuminate\Support\Collection;

/**
 * The root of the content tree, its data lives in content/site.json.
 */
class Site
{
    use HasFields;

    private ?Data $data = null;
    private ?Collection $children = null;
    private ?Blueprint $blueprint = null;

    public function __construct(public readonly string $contentDir)
    {
    }

    public function data(): Data
    {
        return $this->data ??= new Data(ContentFile::readJson("$this->contentDir/site.json"));
    }

    /**
     * site/blueprints/site.yml
     */
    public function blueprint(): Blueprint
    {
        return $this->blueprint ??= Blueprint::load("site");
    }

    protected function fieldDefinition(string $name): ?Field
    {
        return $this->blueprint()->field($name);
    }

    public function allChildren(): Collection
    {
        return $this->children ??= Page::scan($this->contentDir);
    }

    public function children(): Collection
    {
        return $this->allChildren()->reject(fn(Page $page) => $page->isDraft)->values();
    }

    public function homePage(): ?Page
    {
        return $this->find("/");
    }

    /**
     * Finds a page by its slug path, e.g. "blog/newest_entry".
     * Drafts are included when not specified otherwise and a user is logged in.
     */
    public function find(string $path, ?bool $includeDrafts = null): ?Page
    {
        $includeDrafts ??= User::current() !== null;
        $slugs = array_values(array_filter(explode("/", $path), fn(string $slug) => $slug !== ""));

        if ($slugs === []) {
            $slugs = [Page::HOME_SLUG];
        }

        $page = null;

        foreach ($slugs as $slug) {
            $page = $page === null
                ? $this->allChildren()->first(
                    fn(Page $child) => $child->slug === $slug && ($includeDrafts || !$child->isDraft)
                )
                : $page->child($slug, $includeDrafts);

            if ($page === null) {
                return null;
            }
        }

        return $page;
    }

    /**
     * All pages including drafts, depth first.
     */
    public function index(): Collection
    {
        $pages = new Collection();
        $collect = function (Collection $children) use (&$collect, $pages) {
            foreach ($children as $page) {
                $pages->push($page);
                $collect($page->allChildren());
            }
        };
        $collect($this->allChildren());

        return $pages;
    }

    public function findByUid(string $uid): ?Page
    {
        return $this->index()->first(fn(Page $page) => $page->uid() === $uid);
    }

    public function findFileByUid(string $uid): ?File
    {
        foreach ($this->index() as $page) {
            foreach ($page->files() as $file) {
                if ($file->uid() === $uid) {
                    return $file;
                }
            }
        }

        return null;
    }

    public function update(array $values): void
    {
        $data = array_filter([...$this->data()->toArray(), ...$values], fn($value) => $value !== null);
        ContentFile::write("$this->contentDir/site.json", $data);
        $this->data = null;
    }
}
