<?php
/**
 * @var Blueprint\Page $page
 * @var Blueprint\Site $site
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page->title() ?> | <?= esc($site->data()->name) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/minimaxcss@latest/minimax-starter.css"/>
</head>
<body>
<main>
    <article>
        <?= $slot ?>
    </article>
</main>
</body>
</html>
