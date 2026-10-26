// Code highlighting for the docs: ```php, ```yaml, … blocks (classes from md()) with speed-highlight.
// A module, loaded by site/snippets/layout.php.

// speed-highlight (https://github.com/speed-highlight/core), loaded only on pages with code
const HIGHLIGHT = "https://cdn.jsdelivr.net/npm/@speed-highlight/core@2.1.0/dist/index.js";

// names in ```fences that speed-highlight calls differently
const LANGUAGE_ALIASES = { sh: "bash", shell: "bash", yml: "yaml", javascript: "js", markdown: "md" };

// speed-highlight has no php, a basic grammar: the earliest match wins, on a tie the first rule
const PHP = [
  { expand: "str" },
  // line comments end before ?>
  { type: "cmnt", match: /(\/\/|#(?!\[)).*?(?=\?>|$)/gm },
  { type: "cmnt", match: /\/\*[^]*?(\*\/|$)/g },
  { type: "kwd", match: /<\?(php|=)?|\?>/g },
  { type: "var", match: /\$\w+/g },
  {
    type: "kwd",
    match: /\b(abstract|and|array|as|break|case|catch|class|clone|const|continue|declare|default|do|echo|else|elseif|empty|endforeach|endif|endwhile|enum|extends|final|finally|fn|for|foreach|function|global|if|implements|include|include_once|instanceof|interface|isset|list|match|namespace|new|or|parent|print|private|protected|public|readonly|require|require_once|return|self|static|switch|throw|trait|try|unset|use|while|yield)\b/g,
  },
  { type: "bool", match: /\b(true|false|null)\b/gi },
  { expand: "num" },
  { type: "func", match: /\b[a-zA-Z_]\w*(?=\s*\()/g },
  { type: "class", match: /\b[A-Z]\w*\b/g },
  { type: "oper", match: /->|=>|::|[-+*/%=<>!&|^~?:.]+/g },
];

const blocks = [...document.querySelectorAll(".docs-text pre > code[class]")];

if (blocks.length) {
  const { highlightElement, setLoader, defaultLoader } = await import(HIGHLIGHT);
  setLoader((name) => (name === "php" ? PHP : defaultLoader(name)));

  for (const code of blocks) {
    const name = code.className.trim().split(/\s+/)[0].replace(/^language-/, "");
    // inline mode keeps the <pre> and its styles, only the tokens are colored
    highlightElement(code, LANGUAGE_ALIASES[name] ?? name, { block: false });
  }
}
