<?php

use Blueprint\User;
use Blueprint\Data;

beforeEach(fn() => $this->app = createApp(WEBSITE_PROJECT));

it("serves the home page at /", function () {
    $response = $this->app->request("/");

    expect($response->getStatusCode())->toBe(200)
        ->and(body($response))->toContain("<h2>Hello and welcome</h2>")
        ->and(body($response))->toContain("Welcome");
});

it("redirects /home to /", function () {
    $response = $this->app->request("/home");

    expect($response->getStatusCode())->toBe(301)
        ->and($response->getHeaderLine("Location"))->toBe("/");
});

it("serves folder pages with page.md", function () {
    $response = $this->app->request("/imprint");

    expect($response->getStatusCode())->toBe(200)
        ->and(body($response))->toContain("A very boring page");
});

it("redirects pages with subpages to their trailing slash url", function () {
    $response = $this->app->request("/blog?page=2");

    expect($response->getStatusCode())->toBe(301)
        ->and($response->getHeaderLine("Location"))->toBe("/blog/?page=2");
});

it("redirects pages without subpages to the url without trailing slash", function () {
    $response = $this->app->request("/imprint/");

    expect($response->getStatusCode())->toBe(301)
        ->and($response->getHeaderLine("Location"))->toBe("/imprint");
});

it("serves sub pages with page.json", function () {
    $response = $this->app->request("/blog/newest_entry");

    expect($response->getStatusCode())->toBe(200)
        ->and(body($response))->toContain("Newest Entry");
});

it("combines page.json with page.content.md", function () {
    $page = page("blog/second_newest_entry");

    expect($page->data()->title)->toBe("Test title")
        ->and($page->data()->content)->toStartWith("## This is just a plain content file");
});

it("hides drafts unless a user is logged in", function () {
    expect($this->app->request("/this_is_a_draft_page")->getStatusCode())->toBe(404);

    User::setCurrent(new User(new Data(["email" => "admin"])));

    expect($this->app->request("/this_is_a_draft_page")->getStatusCode())->toBe(200);
});

it("follows redirects.json", function () {
    $response = $this->app->request("/from-foo");

    expect($response->getStatusCode())->toBe(301)
        ->and($response->getHeaderLine("Location"))->toBe("/to-bar");
});

it("responds with 404 for unknown pages", function () {
    expect($this->app->request("/does-not-exist")->getStatusCode())->toBe(404)
        ->and($this->app->request("/01_blog/")->getStatusCode())->toBe(404);
});

it("provides site, user and page helpers", function () {
    expect(site()->data()->name)->toBe("Website fixture")
        ->and(user("admin")->uid())->toBe("adminuid00000001")
        ->and(user())->toBeNull()
        ->and(page("blog")->url())->toBe("/blog/")
        ->and(page("blog")->num)->toBeInt()
        ->and(site()->children()->map->slug->all())->toContain("blog", "about", "home", "imprint");
});
