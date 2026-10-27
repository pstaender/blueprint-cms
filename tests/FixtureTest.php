<?php

beforeEach(fn() => $this->app = createApp(FIXTURE_PROJECT));

it("merges site config with the project config, the project config wins", function () {
    expect($this->app->config->get("custom"))->toBe("project")
        ->and($this->app->isDebug())->toBeTrue()
        ->and($this->app->config->get("panel"))->toBeTrue();
});

it("uses the data returned by a page.php with the template", function () {
    expect(body($this->app->request("/data-page")))->toBe("Data from PHP|");
});

it("renders a page with its template instead of its type's", function () {
    expect(body($this->app->request("/templated")))->toBe("tagged: Templated")
        ->and(body($this->app->request("/templated-php")))->toBe("tagged: templated-php")
        ->and(Blueprint\Page::find("templated")->type())->toBe("page");
});

it("uses the output of a page php file as response", function () {
    expect(body($this->app->request("/echo")))->toBe("echoed by echo");
});

it("lets page php files handle every request method", function () {
    foreach (["POST", "PUT", "PATCH", "DELETE", "OPTIONS"] as $method) {
        expect(body($this->app->request("/echo", $method)))->toBe("echoed by echo")
            ->and(body($this->app->request("/data-page", $method)))->toBe("Data from PHP|");
    }
});

it("ignores php files in the content folder unless content.allowPHP is enabled", function () {
    $app = createApp(FIXTURE_PROJECT, ["content" => ["allowPHP" => false]]);

    expect($app->request("/echo")->getStatusCode())->toBe(404)
        ->and($app->request("/data-page")->getStatusCode())->toBe(404)
        ->and(createApp(FIXTURE_PROJECT, ["content" => ["allowPHP" => ["edit" => []]]])->request("/echo")->getStatusCode())->toBe(200);
});

it("redirects from templates with redirect()", function () {
    $response = $this->app->request("/go");

    expect($response->getStatusCode())->toBe(307)
        ->and($response->getHeaderLine("Location"))->toBe("/tmp_redirect");
});

it("prefers folder pages over file pages with the same slug", function () {
    expect(page("list/sub")->data()->title)->toBe("Folder wins");
});

it("does not count drafts as children for the url", function () {
    expect(Blueprint\Page::find("draft", includeDrafts: true)->url())->toBe("/draft")
        ->and(page("draft"))->toBeNull();
});
