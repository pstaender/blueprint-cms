<?php /* @var Blueprint\Page $page */ ?>
<?php snippet("layout", slots: true) ?>
<?= $page->content() ?>
<?php endsnippet() ?>
