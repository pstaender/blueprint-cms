<?php

declare(strict_types=1);

namespace Blueprint;

/**
 * A snippet with slots (Kirby style), opened by snippet("card", slots: true) and closed by endsnippet():
 *
 *     <?php snippet("card", slots: true) ?>
 *       <?php slot("title") ?><h2>Title</h2><?php endslot() ?>
 *       <?php slot() ?>The text<?php endslot() ?>
 *     <?php endsnippet() ?>
 *
 * The snippet gets `$slot` (the default slot) and `$slots` (`$slots->title()`). Without slot() calls
 * everything between snippet() and endsnippet() is the default slot.
 */
class Snippet
{
    /**
     * @var list<self> the open snippets, nested snippets are possible
     */
    private static array $open = [];

    /**
     * @var array<string, string>
     */
    private array $slots = [];

    private ?string $openSlot = null;

    private function __construct(
        private readonly string|array $name,
        private readonly array $data,
        private readonly bool $return,
    ) {
    }

    public static function begin(string|array $name, array $data = [], bool $return = false): self
    {
        $snippet = new self($name, $data, $return);
        self::$open[] = $snippet;
        ob_start();

        return $snippet;
    }

    public static function end(): ?string
    {
        $snippet = array_pop(self::$open) ?? throw new \LogicException("endsnippet() without snippet(…, slots: true)");

        if ($snippet->openSlot !== null) {
            throw new \LogicException("The slot \"$snippet->openSlot\" isn't closed with endslot()");
        }

        $content = (string) ob_get_clean();

        if ($snippet->slots === []) {
            $snippet->slots["default"] = $content;
        }

        $html = App::instance()->renderSnippet($snippet->name, [
            ...$snippet->data,
            "slot" => $snippet->slots["default"] ?? null,
            "slots" => new Slots($snippet->slots),
        ]);

        if ($snippet->return) {
            return $html;
        }

        echo $html;

        return null;
    }

    public static function slot(string $name = "default"): void
    {
        $snippet = end(self::$open) ?: throw new \LogicException("slot() outside of snippet(…, slots: true)");

        if ($snippet->openSlot !== null) {
            throw new \LogicException("The slot \"$snippet->openSlot\" isn't closed with endslot()");
        }

        $snippet->openSlot = $name;
        ob_start();
    }

    public static function endSlot(): void
    {
        $snippet = end(self::$open);

        if ($snippet === false || $snippet->openSlot === null) {
            throw new \LogicException("endslot() without slot()");
        }

        $snippet->slots[$snippet->openSlot] = (string) ob_get_clean();
        $snippet->openSlot = null;
    }
}
