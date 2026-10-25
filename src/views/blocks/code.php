<pre><code<?php if ($block->language()->isNotEmpty()): ?> class="language-<?= $block->language() ?>"<?php endif ?>><?= esc($block->code()->value()) ?></code></pre>
