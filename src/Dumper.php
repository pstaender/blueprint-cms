<?php

namespace Blueprint;

use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;
use Symfony\Component\VarDumper\VarDumper;

/**
 * The html output of dump() and dd() (symfony/var-dumper) with an own light/dark theme, cli output stays as it is.
 */
class Dumper extends HtmlDumper
{
    public const STYLES = [
        "default" => "color:var(--dump-text); font:13px/1.5 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; white-space:pre-wrap; word-break:break-all; position:relative; z-index:99999",
        "num" => "color:var(--dump-num)",
        "const" => "color:var(--dump-const); font-weight:600",
        "virtual" => "font-style:italic",
        "str" => "color:var(--dump-str)",
        "note" => "color:var(--dump-note)",
        "ref" => "color:var(--dump-dimmed)",
        "public" => "color:var(--dump-text)",
        "protected" => "color:var(--dump-text)",
        "private" => "color:var(--dump-text)",
        "meta" => "color:var(--dump-meta)",
        "key" => "color:var(--dump-key)",
        "index" => "color:var(--dump-num)",
        "ellipsis" => "color:var(--dump-dimmed)",
        "ns" => "user-select:none",
        "label" => "color:var(--dump-dimmed)",
    ];

    private const CSS = <<<CSS
        pre.sf-dump {
          --dump-bg: #f7f7f8; --dump-border: #e2e2e5; --dump-text: #1c1c1e; --dump-dimmed: #8b8b90;
          --dump-str: #1a7f37; --dump-num: #0550ae; --dump-const: #953800; --dump-key: #8250df;
          --dump-note: #0969da; --dump-meta: #cf222e;
        }
        @media (prefers-color-scheme: dark) {
          pre.sf-dump {
            --dump-bg: #1c1c1e; --dump-border: #3a3a3e; --dump-text: #ececee; --dump-dimmed: #8b8b90;
            --dump-str: #7ee787; --dump-num: #79c0ff; --dump-const: #ffa657; --dump-key: #d2a8ff;
            --dump-note: #58a6ff; --dump-meta: #ff7b72;
          }
        }
        pre.sf-dump { margin: 16px; padding: 12px 16px; border: 1px solid var(--dump-border); border-radius: 8px; background: var(--dump-bg); }
        pre.sf-dump .sf-dump-hover:hover { background: var(--dump-border); color: inherit !important; border-radius: 3px; }
        pre.sf-dump .sf-dump-toggle span { color: var(--dump-dimmed); }
        CSS;

    public static function register(): void
    {
        $cloner = new VarCloner();
        $isCli = in_array(PHP_SAPI, ["cli", "phpdbg", "embed"], true);
        $dumper = $isCli ? new CliDumper() : new self();

        VarDumper::setHandler(function (mixed $value, ?string $label = null) use ($cloner, $dumper) {
            $data = $cloner->cloneVar($value);
            $dumper->dump($label === null ? $data : $data->withContext(["label" => $label]));
        });
    }

    public function __construct($output = null, ?string $charset = null, int $flags = 0)
    {
        parent::__construct($output, $charset, $flags);
        $this->setStyles(self::STYLES);
    }

    protected function getDumpHeader(): string
    {
        $isNew = $this->dumpHeader === null;
        $header = parent::getDumpHeader();

        return $isNew ? $this->dumpHeader = str_replace("</style>", preg_replace('/\s+/', " ", self::CSS) . "</style>", $header) : $header;
    }
}
