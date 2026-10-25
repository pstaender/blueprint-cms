<?php
$url = (string) $block->url()->value();
$embed = match (true) {
    (bool) preg_match('#(?:youtube\.com/watch\?v=|youtu\.be/)([\w-]+)#', $url, $m) => "https://www.youtube-nocookie.com/embed/$m[1]",
    (bool) preg_match('#vimeo\.com/(\d+)#', $url, $m) => "https://player.vimeo.com/video/$m[1]",
    default => null,
};
?>
<?php if ($url !== ""): ?>
<figure class="video">
  <?php if ($embed): ?>
    <iframe src="<?= esc($embed) ?>" allowfullscreen loading="lazy"></iframe>
  <?php else: ?>
    <video src="<?= esc($url) ?>" controls></video>
  <?php endif ?>
  <?php if ($block->caption()->isNotEmpty()): ?>
    <figcaption><?= $block->caption() ?></figcaption>
  <?php endif ?>
</figure>
<?php endif ?>
