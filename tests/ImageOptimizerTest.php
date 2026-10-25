<?php

use Blueprint\ImageOptimizer;

beforeEach(function () {
    $this->dir = copyProject(BLUEPRINT_PROJECT);
    $this->app = createApp($this->dir);
});

afterEach(fn() => removeProject($this->dir));

it("converts used images to cached AVIF files", function () {
    $url = optimized_content_image(page("builder")->file("photo.png"));
    $file = "$this->dir/public$url";

    expect($url)->toMatch('#^/media/images/[0-9a-f]{64}/photo\.avif$#')
        ->and(substr((string) file_get_contents($file), 4, 8))->toBe("ftypavif")
        ->and(optimized_content_image("/builder/photo.png"))->toBe($url)
        ->and(optimized_content_image("builder/photo.png"))->toBe($url)
        ->and(optimized_content_image("file://photo00001"))->toBe($url)
        ->and(optimized_content_image("/builder/photo.png", quality: 40))->not->toBe($url);

    $modified = filemtime($file);
    optimized_content_image("/builder/photo.png");

    expect(filemtime($file))->toBe($modified);
})->skip(!ImageOptimizer::isSupported(), "no AVIF support");

it("keeps files that can't or mustn't be converted", function () {
    copy("$this->dir/content/builder/photo.png", "$this->dir/content/builder/unused.png");

    expect(optimized_content_image("/builder/unused.png"))->toBe("/builder/unused.png")
        ->and(optimized_content_image("/builder/notes.txt"))->toBe("/builder/notes.txt")
        ->and(optimized_content_image("/nope/missing.png"))->toBe("/nope/missing.png")
        ->and(is_dir("$this->dir/public/media"))->toBeFalse();
});

it("replaces local images in html", function () {
    $html = optimized_content_images('<p><img src="/builder/photo.png" alt="x"> <img src="https://example.com/a.png"> <img src="/nope.png"></p>');

    expect($html)->toMatch('#<img src="/media/images/[0-9a-f]{64}/photo\.avif" alt="x">#')
        ->toContain('<img src="https://example.com/a.png">')
        ->toContain('<img src="/nope.png">');
})->skip(!ImageOptimizer::isSupported(), "no AVIF support");

it("optimizes all images of rendered pages with optimizeContentImages", function () {
    file_put_contents("$this->dir/site/templates/builder.php", '<?= $page->body() ?>');

    expect(body($this->app->request("/builder")))->toContain('src="/builder/photo.png"');

    $app = createApp($this->dir, ["optimizeContentImages" => true]);

    expect(body($app->request("/builder")))->toMatch('#src="/media/images/[0-9a-f]{64}/photo\.avif"#');
})->skip(!ImageOptimizer::isSupported(), "no AVIF support");
