<?php

use Blueprint\Block;
use Blueprint\Blueprint;
use Blueprint\File;
use Blueprint\Page;
use Blueprint\Uid;

beforeEach(fn() => $this->app = createApp(BLUEPRINT_PROJECT));

describe("fields: true", function () {
    it("places all unused field definitions in their order", function () {
        $blueprint = Blueprint::load("pages/rest");

        expect(array_keys($blueprint->tab("content")["columns"][1]["fields"]))->toBe(["seoTitle", "seoDescription", "extra"])
            ->and(array_keys($blueprint->tab("meta")["columns"][0]["fields"]))->toBe(["date"])
            ->and(array_keys($blueprint->fields()))->toBe(["title", "intro", "date", "seoTitle", "seoDescription", "extra"]);
    });
});

describe("blocks and layouts", function () {
    it("normalizes fieldsets", function () {
        $blueprint = Blueprint::load("pages/builder");
        $fieldsets = $blueprint->field("body")->get("fieldsets");

        expect(array_keys($fieldsets))->toBe(["heading", "text", "image"])
            ->and($fieldsets["heading"]["label"])->toBe("Heading")
            ->and(array_keys($fieldsets["heading"]["fields"]))->toBe(["level", "text"])
            ->and($fieldsets["image"]["fields"]["image"]["type"])->toBe("filepicker")
            ->and(array_keys($blueprint->field("grid")->get("fieldsets")))->toBe(["heading", "callout"])
            ->and($blueprint->field("grid")->get("fieldsets.callout.label"))->toBe("Callout")
            ->and($blueprint->field("grid")->get("layouts"))->toBe([["1/1"], ["1/2", "1/2"]])
            ->and(array_keys($blueprint->field("everything")->get("fieldsets")))->toBe(\Blueprint\BlueprintNormalizer::DEFAULT_FIELDSETS);
    });

    it("stops recursive fieldsets", function () {
        $inner = Blueprint::load("pages/builder")->field("nested")->get("fieldsets.section.fields.inner.fieldsets");

        expect($inner["section"]["fields"]["error"]["type"])->toBe("info")
            ->and($inner["text"]["fields"])->toHaveKey("text");
    });

    it("renders blocks with the core and site snippets", function () {
        $html = (string) page("builder")->body();

        expect($html)->toContain("<h3>Hello &lt;World&gt;</h3>")
            ->toContain("<strong>text</strong>")
            ->not->toContain("Hidden")
            ->toContain('<img src="/builder/photo.png" alt="A photo">');
    });

    it("gives blocks field methods", function () {
        $blocks = page("builder")->body()->toBlocks();

        expect($blocks)->toHaveCount(4)
            ->and($blocks->first())->toBeInstanceOf(Block::class)
            ->and($blocks->first()->type())->toBe("heading")
            ->and($blocks->first()->id())->toBe("b1")
            ->and((string) $blocks->first()->level())->toBe("h3")
            ->and($blocks->get(1)->isHidden())->toBeTrue()
            ->and($blocks->last()->image()->toFile()->filename())->toBe("photo.png");
    });

    it("renders layouts", function () {
        $html = (string) page("builder")->grid();
        $layout = page("builder")->grid()->toLayouts()->first();

        expect($html)->toContain("grid-column: span 6")
            ->toContain("<aside>Note &amp; more</aside>")
            ->toContain("<h2>Side</h2>")
            ->and($layout->columns()->first()->width())->toBe("1/2")
            ->and($layout->columns()->first()->blocks()->toBlocks()->first()->type())->toBe("callout");
    });

    it("validates block content", function () {
        $errors = Blueprint::load("pages/builder")->validate([
            "grid" => [["columns" => [["width" => "1/1", "blocks" => [["type" => "callout", "content" => []]]]]]],
        ]);

        expect($errors)->toBe(["grid.0.0.0.text" => ["Text is required"]]);
    });
});

describe("files", function () {
    it("lists the files of a page without content and meta files", function () {
        $files = page("builder")->files();

        expect($files->map(fn(File $file) => $file->filename())->all())->toBe(["notes.txt", "photo.png"])
            ->and(page("builder")->file("photo.png")->type())->toBe("image")
            ->and(page("builder")->file("photo.png")->dimensions())->toBe(["width" => 40, "height" => 30])
            ->and(page("builder")->file("photo.png")->url())->toBe("/builder/photo.png")
            ->and(page("builder")->file("photo.png")->blueprint()->name)->toBe("files/image")
            ->and((string) page("builder")->file("photo.png")->alt())->toBe("Photo alt")
            ->and(page("builder")->file("page.json"))->toBeNull();
    });

    it("serves files under the page url", function () {
        $response = $this->app->request("/builder/photo.png");

        expect($response->getStatusCode())->toBe(200)
            ->and($response->getHeaderLine("Content-Type"))->toBe("image/png")
            ->and($response->getHeaderLine("Content-Disposition"))->toStartWith("inline")
            ->and($response->getHeaderLine("X-Content-Type-Options"))->toBe("nosniff")
            ->and($this->app->request("/builder/photo.png.json")->getStatusCode())->toBe(404)
            ->and($this->app->request("/builder/page.json")->getStatusCode())->toBe(404);
    });

    it("resolves file references", function () {
        $page = page("builder");
        $field = fn($value) => new \Blueprint\FieldValue("files", $value, new \Blueprint\Field(["name" => "files", "type" => "filepicker"]), $page);

        expect($field(["photo.png"])->toFile()->filename())->toBe("photo.png")
            ->and($field(["builder/notes.txt"])->toFile()->filename())->toBe("notes.txt")
            ->and($field(["file://photo00001"])->toFile()->filename())->toBe("photo.png")
            ->and($field("photo.png, missing.png")->toFiles())->toHaveCount(1)
            ->and((string) $field(["photo.png"]))->toBe("/builder/photo.png");
    });
});

describe("changing content", function () {
    beforeEach(function () {
        $this->dir = copyProject(BLUEPRINT_PROJECT);
        $this->app = createApp($this->dir);
    });

    afterEach(fn() => removeProject($this->dir));

    it("adds uids to pages when they are saved or created", function () {
        page("about")->update(["title" => "About"]);
        $created = Page::create(null, "new", ["title" => "New"]);

        expect(page("about")->uid())->toMatch('/^[a-z0-9]{10}$/')
            ->and($created->uid())->toMatch('/^[a-z0-9]{10}$/')
            ->and(site()->findByUid(page("about")->uid())->id())->toBe("about");
    });

    it("finds pages by uid references", function () {
        $field = new \Blueprint\FieldValue("related", ["page://builder001", "about"], new \Blueprint\Field(["name" => "related", "type" => "pagepicker"]));

        expect($field->toPages()->map(fn(Page $page) => $page->id())->all())->toBe(["builder", "about"]);
    });

    it("changes the slug and keeps the status", function () {
        $page = page("about")->changeStatus("listed")->changeSlug("about-us");

        expect($page->slug)->toBe("about-us")
            ->and($page->num)->toBe(1)
            ->and(is_dir("$this->dir/content/01_about-us"))->toBeTrue()
            ->and(fn() => page("showcase")->changeSlug("Bad Slug"))->toThrow(InvalidArgumentException::class)
            ->and(fn() => page("home")->changeSlug("start"))->toThrow(LogicException::class);
    });

    it("changes the type", function () {
        page("about")->changeType("article");

        expect(page("about")->type())->toBe("article")
            ->and(page("about")->blueprint()->name)->toBe("pages/article");

        page("about")->changeType("page");

        expect(page("about")->data()->type)->toBeNull()
            ->and(page("about")->type())->toBe("page");
    });

    it("stores files with sanitized names, uids and accept rules", function () {
        $file = File::create(page("builder"), "Mein Foto (1).TXT", "hi", ["template" => "default"]);

        expect($file->filename())->toBe("mein-foto-1.txt")
            ->and($file->uid())->toMatch('/^[a-z0-9]{10}$/')
            ->and(fn() => File::create(page("builder"), "evil.php", "<?php"))->toThrow(InvalidArgumentException::class)
            ->and(fn() => File::create(page("builder"), "x.md", "# x"))->toThrow(InvalidArgumentException::class)
            ->and(fn() => File::create(page("builder"), "notes.txt", "again"))->toThrow(InvalidArgumentException::class)
            ->and(fn() => File::create(page("builder"), "fake.txt", "no image", ["template" => "image"]))->toThrow(InvalidArgumentException::class)
            ->and(file_exists("$this->dir/content/builder/fake.txt"))->toBeFalse();

        $renamed = $file->changeName("Renamed");

        expect($renamed->filename())->toBe("renamed.txt")
            ->and(is_file("$this->dir/content/builder/renamed.txt.json"))->toBeTrue();

        $renamed->delete();

        expect(page("builder")->file("renamed.txt"))->toBeNull()
            ->and(file_exists("$this->dir/content/builder/renamed.txt.json"))->toBeFalse();
    });
});

it("uses the type for templates with page.php and default.php as fallbacks", function () {
    $dir = copyProject(FIXTURE_PROJECT);

    try {
        $app = createApp($dir);
        file_put_contents("$dir/site/templates/page.php", "page template");
        file_put_contents("$dir/site/templates/blogEntry.php", "blog entry template");
        file_put_contents("$dir/content/entry.md", "---\ntype: blogEntry\n---\n");

        expect($app->templateFile("blogEntry"))->toEndWith("/blogEntry.php")
            ->and($app->templateFile("unknown"))->toEndWith("/page.php")
            ->and(body($app->request("/entry")))->toBe("blog entry template")
            ->and(body($app->request("/list/")))->toBe("page template");
    } finally {
        removeProject($dir);
    }
});

it("generates uids", function () {
    expect(Uid::generate())->toMatch('/^[a-z0-9]{10}$/')
        ->and(Uid::parse("page://abc123"))->toBe(["page", "abc123"])
        ->and(Uid::parse("blog/entry"))->toBeNull();
});

describe("file access and links", function () {
    it("only serves files that are used, except for logged in users", function () {
        expect($this->app->request("/builder/photo.png")->getStatusCode())->toBe(200)
            ->and(page("builder")->file("photo.png")->isUsed())->toBeTrue()
            ->and(page("builder")->file("notes.txt")->isUsed())->toBeFalse()
            ->and($this->app->request("/builder/notes.txt")->getStatusCode())->toBe(404);

        \Blueprint\User::setCurrent(\Blueprint\User::find("editor1"));

        expect($this->app->request("/builder/notes.txt")->getStatusCode())->toBe(200);
    });

    it("counts references from other pages", function () {
        $dir = copyProject(BLUEPRINT_PROJECT);

        try {
            createApp($dir);
            $notes = page("builder")->file("notes.txt");
            $notes->update([]);

            page("about")->update(["text" => "see builder/notes.txt"]);
            expect(page("builder")->file("notes.txt")->isUsed())->toBeTrue();

            page("about")->update(["text" => null, "files" => ["file://" . $notes->uid()]]);
            expect(page("builder")->file("notes.txt")->isUsed())->toBeTrue();

            page("about")->update(["files" => null, "text" => "see mybuilder/notes.txt and notes.txt.bak"]);
            expect(page("builder")->file("notes.txt")->isUsed())->toBeFalse();
        } finally {
            removeProject($dir);
        }
    });

    it("resolves relative file links in markdown", function () {
        $html = md("![Photo](photo.png) [Notes](builder/notes.txt) [Missing](missing.png) [Abs](/x.png) [Web](https://example.com/photo.png)", page("builder"));

        expect($html)->toContain('src="/builder/photo.png"')
            ->toContain('href="/builder/notes.txt"')
            ->toContain('href="missing.png"')
            ->toContain('href="/x.png"')
            ->toContain('href="https://example.com/photo.png"');
    });

    it("resolves file links in textarea field values", function () {
        $field = new \Blueprint\FieldValue("text", "![Photo](photo.png)", new \Blueprint\Field(["name" => "text", "type" => "textarea"]), page("builder"));

        expect((string) $field)->toContain('<img src="/builder/photo.png" alt="Photo"');
    });
});

describe("content formats", function () {
    beforeEach(function () {
        $this->dir = copyProject(BLUEPRINT_PROJECT);
        $this->app = createApp($this->dir);
    });

    afterEach(fn() => removeProject($this->dir));

    it("converts between the content formats and keeps the data", function () {
        page("about")->update(["content" => "Some **text**"]);
        $about = "$this->dir/content/about";
        $data = page("about")->data()->toArray();

        expect(page("about")->contentFormat())->toBe("json");

        $page = page("about")->changeContentFormat("md");
        expect($page->contentFormat())->toBe("md")
            ->and(is_file("$about/page.json"))->toBeFalse()
            ->and(file_get_contents("$about/page.md"))->toEndWith("---\nSome **text**\n")
            ->and($page->data()->toArray())->toEqualCanonicalizing($data);

        $page = $page->changeContentFormat("json+md");
        expect($page->contentFormat())->toBe("json+md")
            ->and(is_file("$about/page.md"))->toBeFalse()
            ->and(json_decode(file_get_contents("$about/page.json"), true))->not->toHaveKey("content")
            ->and(file_get_contents("$about/page.content.md"))->toBe("Some **text**\n")
            ->and($page->data()->toArray())->toEqualCanonicalizing($data);

        $page = $page->changeContentFormat("json");
        expect($page->contentFormat())->toBe("json")
            ->and(is_file("$about/page.content.md"))->toBeFalse()
            ->and($page->data()->content)->toBe("Some **text**")
            ->and(fn() => $page->changeContentFormat("xml"))->toThrow(InvalidArgumentException::class);
    });

    it("turns single file pages into folders", function () {
        file_put_contents("$this->dir/content/02_note.md", "---\ntitle: Note\n---\nHello\n");
        $page = page("note")->changeContentFormat("json");

        expect($page->root)->toBe("$this->dir/content/02_note")
            ->and($page->num)->toBe(2)
            ->and(file_exists("$this->dir/content/02_note.md"))->toBeFalse()
            ->and(page("note")->data()->title)->toBe("Note")
            ->and(page("note")->data()->content)->toBe("Hello")
            ->and(page("note")->uid())->toMatch('/^[a-z0-9]{10}$/');
    });
});
