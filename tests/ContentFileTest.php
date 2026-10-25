<?php

use Blueprint\ContentFile;
use Blueprint\Data;

it("parses front matter and markdown body", function () {
    $data = new Data(ContentFile::parseMarkdown(<<<MD
    ---
    title: Welcome
    template: framework
    some:
      nested:
        - value
    ---
    ## Hello and welcome
    MD));

    expect($data->title)->toBe("Welcome")
        ->and($data->template)->toBe("framework")
        ->and($data->some->nested)->toBe(["value"])
        ->and($data->content)->toBe("## Hello and welcome");
});

it("treats markdown without front matter as content", function () {
    expect(ContentFile::parseMarkdown("## Imprint\n\nText\n"))->toBe(["content" => "## Imprint\n\nText"]);
});

it("returns null for missing data keys", function () {
    expect((new Data(["a" => 1]))->missing)->toBeNull();
});
