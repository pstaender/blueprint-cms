<?php

use Blueprint\App;
use Blueprint\FieldValue;
use Blueprint\Page;
use Blueprint\User;
use Blueprint\Values;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

beforeEach(function () {
    createApp(BLUEPRINT_PROJECT);
    $this->page = page("showcase");
});

it("returns field values from field methods", function () {
    expect($this->page->createdAt())->toBeInstanceOf(FieldValue::class)
        ->and($this->page->createdAt()->value())->toBe("2026-10-25")
        ->and($this->page->createdAt()->type())->toBe("date")
        ->and($this->page->createdAt()->field->name)->toBe("createdAt")
        ->and($this->page->field("createdAt")->value())->toBe("2026-10-25");
});

it("escapes text output", function () {
    expect((string) $this->page->title())->toBe("Fish &amp; Chips &lt;b&gt;")
        ->and((string) $this->page->undefinedField())->toBe("&lt;i&gt;not in blueprint&lt;/i&gt;")
        ->and(esc($this->page->title()))->toBe("Fish &amp; Chips &lt;b&gt;")
        ->and($this->page->data()->title)->toBe("Fish & Chips <b>");
});

it("formats dates and times", function () {
    expect((string) $this->page->createdAt())->toBe("2026-10-25")
        ->and((string) $this->page->startsAt())->toBe("2026-10-25 18:30")
        ->and((string) $this->page->publishedAt())->toBe("02.01.2026")
        ->and((string) $this->page->opensAt())->toBe("09:15")
        ->and((string) $this->page->createdAt()->format("Y.m.d"))->toBe("2026.10.25")
        ->and($this->page->createdAt()->toDate())->toBeInstanceOf(Carbon::class)
        ->and($this->page->createdAt()->isPast())->toBeTrue()
        ->and((string) $this->page->createdAt()->addDay()->format("d."))->toBe("26.");
});

it("renders markdown safely and keeps writer html", function () {
    expect((string) $this->page->text())
        ->toContain("<strong>bold</strong>")
        ->toContain("&lt;script")
        ->not->toContain("<script")
        ->and((string) $this->page->html())->toBe("<p>Rich <strong>text</strong></p>");
});

it("handles empty values", function () {
    $empty = $this->page->seoTitle();

    expect($empty->isEmpty())->toBeTrue()
        ->and($this->page->title()->isNotEmpty())->toBeTrue()
        ->and((string) $empty)->toBe("")
        ->and((string) $empty->format("Y"))->toBe("")
        ->and((string) $empty->or($this->page->title()))->toBe("Fish &amp; Chips &lt;b&gt;")
        ->and((string) $empty->or("Fallback & more"))->toBe("Fallback &amp; more")
        ->and(page("about")->blueprint()->name)->toBe("pages/default")
        ->and((string) page("about")->title())->toBe("About");
});

it("falls back to the slug as title", function () {
    expect((string) (new Page(BLUEPRINT_PROJECT . "/content/none", "none"))->title())->toBe("none");
});

it("converts values", function () {
    expect($this->page->featured()->toBool())->toBeTrue()
        ->and((string) $this->page->featured())->toBe("true")
        ->and($this->page->price()->toFloat())->toBe(9.5)
        ->and($this->page->price()->toInt())->toBe(9)
        ->and((string) $this->page->price())->toBe("9.5");
});

it("outputs option labels", function () {
    expect((string) $this->page->category())->toBe("Review")
        ->and((string) $this->page->colors())->toBe("Red, Blue")
        ->and($this->page->category()->value())->toBe("review");
});

it("handles lists", function () {
    $tags = $this->page->tags();

    expect($tags->toArray())->toBe(["fish", "chips"])
        ->and(count($tags))->toBe(2)
        ->and((string) $tags)->toBe("fish, chips")
        ->and($tags->first())->toBeInstanceOf(FieldValue::class)
        ->and($tags->toCollection())->toBeInstanceOf(Collection::class)
        ->and((string) $this->page->list())->toBe("a, b");

    $items = [];
    foreach ($tags as $tag) {
        $items[] = (string) $tag->upper();
    }

    expect($items)->toBe(["FISH", "CHIPS"]);
});

it("passes string methods to Stringable and keeps the result escaped", function () {
    expect((string) $this->page->title()->upper())->toBe("FISH &amp; CHIPS &lt;B&gt;")
        ->and($this->page->title()->length())->toBe(16)
        ->and($this->page->title()->contains("Chips"))->toBeTrue();
});

it("resolves pages and users", function () {
    expect($this->page->related()->toPages()->map(fn(Page $page) => $page->id())->all())->toBe(["about"])
        ->and($this->page->related()->toPage()->id())->toBe("about")
        ->and((string) $this->page->related())->toBe("About")
        ->and($this->page->author()->toUser())->toBeInstanceOf(User::class)
        ->and((string) $this->page->author())->toBe("editor@example.com");
});

it("never outputs passwords", function () {
    expect((string) $this->page->secret())->toBe("")
        ->and($this->page->secret()->value())->toBe("hunter2");
});

it("gives structure rows and objects field methods", function () {
    $links = $this->page->links();
    $first = $links->first();

    expect($first)->toBeInstanceOf(Values::class)
        ->and(count($links))->toBe(2)
        ->and($first->url()->type())->toBe("url")
        ->and((string) $first->url())->toBe("https://example.com/?a=1&amp;b=2")
        ->and((string) $links->toCollection()->last()->label())->toBe("Other")
        ->and((string) $links)->toBe("")
        ->and((string) $this->page->address()->city())->toBe("Berlin")
        ->and($this->page->address()->toValues()->street()->value())->toBe("Main Street 1");
});

it("gives site and users field methods", function () {
    $site = site();
    $user = User::find("editor1");

    expect((string) $site->name())->toBe("Blueprint fixture")
        ->and((string) $user->field("email"))->toBe("editor@example.com")
        ->and($user->bio()->isEmpty())->toBeTrue();
});

it("renders field values in templates", function () {
    expect(body(App::instance()->request("/showcase")))->toBe("Fish &amp; Chips &lt;b&gt;|25.10.2026");
});
