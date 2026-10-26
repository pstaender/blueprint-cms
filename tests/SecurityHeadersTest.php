<?php

use Blueprint\File;
use Blueprint\Panel;
use Blueprint\User;

beforeEach(function () {
    $this->dir = copyProject(BLUEPRINT_PROJECT);
    $this->app = createApp($this->dir);
});

afterEach(fn() => removeProject($this->dir));

it("sends the content security policy with pages", function () {
    $response = $this->app->request("/about");

    expect($response->getHeaderLine("Content-Security-Policy"))
        ->toContain("default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'")
        ->toContain("frame-ancestors 'self'")
        ->and($response->getHeaderLine("X-Content-Type-Options"))->toBe("nosniff")
        ->and($this->app->request("/missing")->getHeaderLine("Content-Security-Policy"))->toContain("default-src 'self'");
});

it("adds the nonce of csp_nonce() to script-src", function () {
    file_put_contents("$this->dir/content/inline.php", '<script nonce="<?= csp_nonce() ?>">1</script>');
    $response = $this->app->request("/inline");
    preg_match('/nonce="([^"]+)"/', body($response), $matches);

    expect($matches[1])->not->toBeEmpty()
        ->and($response->getHeaderLine("Content-Security-Policy"))->toContain("script-src 'self' 'nonce-$matches[1]';")
        ->and($this->app->request("/about")->getHeaderLine("Content-Security-Policy"))->not->toContain("nonce-");
});

it("configures the content security policy", function () {
    $app = createApp($this->dir, ["csp" => ["script-src" => "'self' https://cdn.example.com", "frame-src" => false, "upgrade-insecure-requests" => true]]);
    $csp = $app->request("/about")->getHeaderLine("Content-Security-Policy");

    expect($csp)->toContain("script-src 'self' https://cdn.example.com;")
        ->toContain("object-src 'none'")
        ->toEndWith("; upgrade-insecure-requests")
        ->not->toContain("frame-src")
        ->and(createApp($this->dir, ["csp" => false])->request("/about")->hasHeader("Content-Security-Policy"))->toBeFalse();
});

it("serves files that aren't shown inline as application/octet-stream", function () {
    // detected as application/javascript, without the shebang it would be text/plain (refused as script with nosniff)
    File::create(page("builder"), "script.js", "#!/usr/bin/env node\nalert(1)\n");
    File::create(page("builder"), "icon.svg", '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
    User::setCurrent(User::find("editor1"));

    foreach (["/builder/script.js", "/builder/icon.svg"] as $url) {
        $response = $this->app->request($url);

        expect($response->getStatusCode())->toBe(200)
            ->and($response->getHeaderLine("Content-Type"))->toBe("application/octet-stream")
            ->and($response->getHeaderLine("Content-Disposition"))->toStartWith("attachment");
    }

    expect($this->app->request("/builder/notes.txt")->getHeaderLine("Content-Type"))->toStartWith("text/plain");
});

it("sends a strict content security policy with the panel", function () {
    $shell = $this->app->request("/panel/pages/about");
    [$status] = api($this->app, "GET", "/nope");
    $api = $this->app->request("/panel/api/session");

    expect($shell->getHeaderLine("Content-Security-Policy"))->toBe(Panel::CSP)
        ->toContain("frame-ancestors 'none'")
        ->and($api->getHeaderLine("Content-Security-Policy"))->toBe("default-src 'none'; frame-ancestors 'none'")
        ->and($api->getHeaderLine("X-Content-Type-Options"))->toBe("nosniff");
});
