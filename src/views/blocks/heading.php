<?php $level = in_array((string) $block->level()->value(), ["h1", "h2", "h3", "h4", "h5", "h6"], true) ? $block->level()->value() : "h2" ?>
<<?= $level ?>><?= $block->text() ?></<?= $level ?>>
