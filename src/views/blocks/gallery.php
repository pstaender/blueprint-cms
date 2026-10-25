<figure class="gallery">
  <ul>
    <?php foreach ($block->images()->toFiles() as $image): ?>
      <li><img src="<?= esc($image->url()) ?>" alt="<?= $image->alt() ?>"></li>
    <?php endforeach ?>
  </ul>
  <?php if ($block->caption()->isNotEmpty()): ?>
    <figcaption><?= $block->caption() ?></figcaption>
  <?php endif ?>
</figure>
