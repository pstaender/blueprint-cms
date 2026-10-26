<?php

// Renders the text and all panel icons (alphabetically), read from the panel's source (panel/src/icons.json)
$icons = json_decode(file_get_contents(dirname(__DIR__, 3) . "/panel/src/icons.json"), true);
ksort($icons);
$items = [];

foreach ($icons as $name => $icon) {
    if ($name === "blank") {
        continue;
    }

    $icon = is_string($icon) ? ["d" => $icon] : $icon;
    $paths = (isset($icon["fill"]) ? '<path class="icon-fill" d="' . esc($icon["fill"]) . '"/>' : "")
        . ($icon["d"] !== "" ? '<path d="' . esc($icon["d"]) . '"/>' : "");
    $items[] = '<li><svg viewBox="0 0 24 24" aria-hidden="true">' . $paths . '</svg><code>' . esc($name) . '</code></li>';
}

// md() escapes html, so the icon list is output after the text instead of being appended to it
snippet("layout", slots: true) ?>
<?= md($page->data()->content) ?>

<ul class="icon-list"><?= implode("", $items) ?></ul>
<?php endsnippet() ?>
