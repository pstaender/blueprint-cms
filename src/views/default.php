<?php /** Fallback template, used when site/templates/default.php does not exist. */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($page->title()) ?> | <?= esc($site->data()->name) ?></title>
</head>
<body>
    <h1><?= esc($page->title()) ?></h1>
    <?= md($page->data()->content) ?>
</body>
</html>
