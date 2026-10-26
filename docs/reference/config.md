---
title: Config options
sort: 5
---
See [Configuration guide](/guides/configuration) for how config files are merged.

| Option | Default | |
|---|---|---|
| `debug` | `false` | shows errors with stack traces |
| `panel` | `true` | enables the [panel](/guides/panel) at `/panel` |
| `contentFolder` | `"content"` | relative to the project dir or absolute |
| `siteFolder` | `"site"` | relative to the project dir or absolute |
| `optimizeContentImages` | `false` | converts all local images of rendered pages to AVIF, see [Images](/guides/images) |
| `markdown.extra` | `false` | [Markdown Extra](https://michelf.ca/projects/php-markdown/extra/) for `md()`, textarea and writer fields: tables, footnotes, definition lists, fenced code blocks with attributes, `{#id .class}`, abbreviations; otherwise plain Markdown |
| `csp` | see below | the `Content-Security-Policy` header of rendered pages, `false` sends none |

Custom options are allowed: `App::instance()->config->get("my.option")`.

## Content Security Policy

`csp` maps directives to their sources. Config files are merged recursively, so a project only lists the directives it changes; `false` removes a directive, `true` adds one without sources:

```php
return [
    "csp" => [
        "script-src" => "'self' https://cdn.jsdelivr.net",
        "frame-src" => false,
        "upgrade-insecure-requests" => true,
    ],
];
```

The default allows scripts, styles, fonts and requests only from the site itself (styles also inline), images from the site, `data:` and `https:`, media from the site and `https:`, frames of YouTube (`youtube-nocookie.com`) and Vimeo for the video block, no plugins (`object-src 'none'`), and framing only by the site itself:

```
default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:;
media-src 'self' https:; frame-src https://www.youtube-nocookie.com https://player.vimeo.com;
object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'
```

Inline scripts need the nonce of the request: `<script nonce="<?= csp_nonce() ?>">…</script>` (it is added to `script-src`). Event handler attributes (`onclick="…"`) are blocked. The panel has its own, stricter policy (it can't be framed). Static sites built with `static:build` don't get the header, their web server has to send it.
