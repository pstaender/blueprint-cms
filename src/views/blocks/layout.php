<?php /** @var Blueprint\Layout $layout */ ?>
<section class="layout" style="display: grid; grid-template-columns: repeat(12, 1fr); gap: 1.5rem">
  <?php foreach ($layout->columns() as $column): ?>
    <div class="column" style="grid-column: span <?= $column->span() ?>">
      <?= $column->blocks() ?>
    </div>
  <?php endforeach ?>
</section>
