<?php snippet("layout", slots: true) ?>
<?= md($page->data()->content ?? "") ?>
<?php endsnippet() ?>
