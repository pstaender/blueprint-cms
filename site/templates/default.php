<?php partial("head") ?>
<main>
    <h1><?= esc($page->title()) ?></h1>
    <?= md($page->data()->content) ?>

    <?php if ($page->hasChildren()): ?>
        <ul>
            <?php foreach ($page->children() as $child): ?>
                <li><a href="<?= esc($child->url()) ?>"><?= esc($child->title()) ?></a></li>
            <?php endforeach ?>
        </ul>
    <?php endif ?>
</main>
<?php partial("footer") ?>
