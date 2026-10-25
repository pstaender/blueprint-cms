<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($page?->title()) ?> | <?= esc($site->data()->name) ?></title>
</head>
<body>
    <nav>
        <a href="/"><?= esc($site->data()->name) ?></a>
        <?php foreach ($site->children()->filter->isListed() as $item): ?>
            <a href="<?= esc($item->url()) ?>"><?= esc($item->title()) ?></a>
        <?php endforeach ?>
    </nav>
