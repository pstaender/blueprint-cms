<?php

use Blueprint\Blueprint;
use Blueprint\BlueprintNormalizer;
use Blueprint\Field;
use Blueprint\User;

beforeEach(fn() => createApp(BLUEPRINT_PROJECT));

it("places field definitions in tabs and columns by reference", function () {
    $blueprint = Blueprint::load("pages/article");
    $content = $blueprint->tab("content");

    expect(array_keys($blueprint->tabs()))->toBe(["content", "seo"])
        ->and($content["label"])->toBe("Content")
        ->and($content["columns"][0]["width"])->toBe("2/3")
        ->and(array_keys($content["columns"][0]["fields"]))->toBe(["title", "links", "text"])
        ->and(array_keys($content["columns"][1]["fields"]))->toBe(["date", "category", "email"])
        ->and($content["columns"][0]["fields"]["text"]["type"])->toBe("textarea")
        ->and($blueprint->tab("seo")["label"])->toBe("Search engines")
        ->and($blueprint->tab())->toBe($content);
});

it("collects all fields of all tabs", function () {
    $blueprint = Blueprint::load("pages/article");

    expect(array_keys($blueprint->fields()))
        ->toBe(["title", "links", "text", "date", "category", "email", "seoTitle", "seoDescription", "subpages"])
        ->and($blueprint->field("email")->type)->toBe("email")
        ->and($blueprint->field("SEOTITLE")->label())->toBe("SEO title")
        ->and($blueprint->field("seoDescription")->label())->toBe("Seo description")
        ->and($blueprint->field("links")->fields()["url"])->toBeInstanceOf(Field::class);
});

it("wraps loose fields and columns in a main tab", function () {
    $loose = Blueprint::load("pages/loose");
    $columns = Blueprint::load("pages/columns");

    expect(array_keys($loose->tabs()))->toBe(["main"])
        ->and($loose->field("text")->type)->toBe("text")
        ->and($loose->title())->toBe("Loose")
        ->and($columns->tab("main")["columns"][1]["fields"])->toBe([])
        ->and($columns->tab("main")["columns"][1]["width"])->toBe("1/2");
});

it("adds the subpages field unless the blueprint has a pagelist or removes it", function () {
    $loose = Blueprint::load("pages/loose");
    $columns = $loose->tab("main")["columns"];

    expect(array_keys(end($columns)["fields"]))->toBe(["subpages"])
        ->and(end($columns)["width"])->toBe("1/1")
        ->and($loose->field("subpages")->type)->toBe("pagelist")
        ->and($loose->field("subpages")->label())->toBe("Subpages")
        ->and(Blueprint::load("site")->field("subpages")->label())->toBe("Pages")
        ->and(Blueprint::load("pages/legacy")->field("subpages"))->toBeNull();
});

it("removes inherited fields with false", function () {
    $blueprint = Blueprint::load("pages/extended");

    expect($blueprint->field("subpages"))->toBeNull()
        ->and($blueprint->field("email"))->toBeNull()
        ->and($blueprint->field("title"))->not->toBeNull()
        ->and(array_column($blueprint->fields(), "type"))->not->toContain("info");
});

it("converts legacy sections into fields", function () {
    $blueprint = Blueprint::load("pages/legacy");

    expect(array_keys($blueprint->fields()))->toBe(["title", "text", "children", "invalid"])
        ->and($blueprint->field("title")->type)->toBe("text")
        ->and($blueprint->field("children")->type)->toBe("pagelist")
        ->and($blueprint->field("children")->label())->toBe("Subpages")
        ->and($blueprint->field("children")->get("template"))->toBe("article")
        ->and($blueprint->field("invalid")->type)->toBe("info");
});

it("replaces invalid fields with error fields", function () {
    $fields = Blueprint::load("pages/broken")->fields();

    expect(array_keys($fields))->toBe(["title", "missing", "title-duplicate", "wrong", "subpages"])
        ->and($fields["missing"]->get("theme"))->toBe("negative")
        ->and($fields["wrong"]->get("text"))->toContain("Invalid field type \"nope\"");
});

it("extends blueprints and normalizes options and status", function () {
    $blueprint = Blueprint::load("pages/extended");

    expect($blueprint->title())->toBe("Extended article")
        ->and($blueprint->get("icon"))->toBe("📝")
        ->and($blueprint->field("seoTitle"))->not->toBeNull()
        ->and($blueprint->get("options.delete"))->toBeFalse()
        ->and($blueprint->get("options.changeSlug"))->toBe(["admin" => true, "*" => false])
        ->and($blueprint->get("options.update"))->toBeNull()
        ->and(array_keys($blueprint->get("status")))->toBe(["draft", "listed"])
        ->and($blueprint->get("status.draft.label"))->toBe("Not ready")
        ->and(array_keys(BlueprintNormalizer::normalizeStatus(["draft" => false, "listed" => true])))->toBe(["draft", "listed"]);
});

it("loads php blueprints", function () {
    $default = Blueprint::load("pages/dynamic")->field("title")->defaultValue();

    expect($default)->toBeInstanceOf(Closure::class)
        ->and($default())->toBe("computed");
});

it("returns the default values", function () {
    expect(Blueprint::load("pages/article")->defaults())->toBe(["date" => "today", "category" => "news"]);
});

it("validates data", function () {
    $blueprint = Blueprint::load("pages/article");

    expect($blueprint->validate([
        "title" => "Hello",
        "category" => "news",
        "links" => [["url" => "https://example.com"]],
    ]))->toBe([]);

    expect($blueprint->validate([
        "email" => "no email",
        "category" => "other",
        "seoTitle" => str_repeat("x", 61),
        "links" => [["url" => "https://example.com"], ["url" => ""]],
    ]))->toBe([
        "title" => ["Title is required"],
        "links.1.url" => ["Url is required"],
        "category" => ["Category is not a valid select value"],
        "email" => ["Email is not a valid email value"],
        "seoTitle" => ["SEO title must be at most 60 characters long"],
    ]);

    expect($blueprint->validate(["title" => "x", "links" => []]))->toBe([]);
    expect((new Field(["name" => "links", "type" => "structure", "min" => 1]))->validate([["a" => 1]]))
        ->toBe([]);
    expect((new Field(["name" => "tags", "type" => "tags", "max" => 1]))->validate("a, b"))
        ->toBe(["tags allows at most 1 entries"]);
});

it("validates patterns with slashes", function (string $pattern) {
    $field = new Field(["name" => "link", "type" => "text", "pattern" => $pattern]);

    expect($field->validate("/kontakt"))->toBe([])
        ->and($field->validate("kontakt"))->toBe(["link doesn't match the required format"]);
})->with(["/.*", "\\/.*", "[/].*", "(/|\\/)[a-z]+"]);

it("keeps an escaped backslash before a slash", function () {
    expect(Field::patternRegex("a\\\\/b"))->toBe("/^(?:a\\\\\\/b)$/u")
        ->and(preg_match(Field::patternRegex("a\\\\/b"), "a\\/b"))->toBe(1);
});

it("chooses page blueprints by template and falls back to the core default", function () {
    expect(page("/")->blueprint()->name)->toBe("pages/article")
        ->and(page("about")->blueprint()->name)->toBe("pages/default")
        ->and(page("about")->blueprint()->field("title")->isRequired())->toBeTrue();
});

it("falls back to the page blueprint marked as default", function () {
    createApp(WEBSITE_PROJECT);

    expect(page("blog/newest_entry")->blueprint()->name)->toBe("pages/page");
});

it("loads site and user blueprints", function () {
    expect(site()->blueprint()->title())->toBe("Website")
        ->and(User::find("editor1")->blueprint()->name)->toBe("users/editor")
        ->and(User::find("admin1")->role())->toBe("user")
        ->and(User::find("admin1")->blueprint()->name)->toBe("users/user")
        ->and(User::find("guest1")->blueprint()->name)->toBe("users/user");
});

it("falls back to the core user blueprint", function () {
    createApp(WEBSITE_PROJECT);

    expect(User::find("guest")->blueprint()->name)->toBe("users/default")
        ->and(User::find("admin")->blueprint()->name)->toBe("users/admin");
});

it("lists blueprint names and ignores invalid names", function () {
    expect(Blueprint::names("pages"))->toContain("pages/article", "pages/default")
        ->and(Blueprint::find("../pages/article"))->toBeNull()
        ->and(fn() => Blueprint::load("pages/nope"))->toThrow(InvalidArgumentException::class);
});
