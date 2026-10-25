<?php
$file = $block->image()->toFile();
$src = $file?->url() ?? $block->src()->value();
$alt = $block->alt()->or($file?->alt() ?? "");
?>
<?php if ($src): ?>
<figure>
  <?php if ($block->link()->isNotEmpty()): ?><a href="<?= $block->link() ?>"><?php endif ?>
  <img src="<?= esc($src) ?>" alt="<?= $alt ?>">
  <?php if ($block->link()->isNotEmpty()): ?></a><?php endif ?>
  <?php if ($block->caption()->isNotEmpty()): ?>
    <figcaption><?= $block->caption() ?></figcaption>
  <?php endif ?>
</figure>
<?php endif ?>
