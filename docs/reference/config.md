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
| `content.optimizeImages` | `false` | converts all local images of rendered pages to AVIF, see [Images](/guides/images) |
| `content.allowPHP` | `false` | executes php files in the content folder (`page.php`, `{slug}.php`): `true`, or `["edit" => [...]]` to also let these roles or users (email or `name`) edit them in the panel, see [Page php files](/guides/templates#page-php-files) |
| `markdown.extra` | `false` | [Markdown Extra](https://michelf.ca/projects/php-markdown/extra/) for `md()`, textarea and writer fields: tables, footnotes, definition lists, fenced code blocks with attributes, `{#id .class}`, abbreviations; otherwise plain Markdown |
| `csp` | `false` | the `Content-Security-Policy` header of rendered pages, opt-in: `true` or an array of directives, see below |

Custom options are allowed: `App::instance()->config->get("my.option")`.

## Content Security Policy

Rendered pages get no `Content-Security-Policy` header unless `csp` is set. `"csp" => true` sends the default policy below. An array maps directives to their sources and is merged over the defaults, so a project only lists the directives it changes; `false` removes a directive, `true` adds one without sources:

```php
return [
    "csp" => [
        "script-src" => "'self' https://cdn.jsdelivr.net",
        "frame-src" => false,
        "upgrade-insecure-requests" => true,
    ],
];
```

The default policy allows scripts, styles, fonts and requests only from the site itself (styles also inline), images from the site, `data:` and `https:`, media from the site and `https:`, frames of YouTube (`youtube-nocookie.com`) and Vimeo for the video block, no plugins (`object-src 'none'`), and framing only by the site itself:

```
default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:;
media-src 'self' https:; frame-src https://www.youtube-nocookie.com https://player.vimeo.com;
object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'
```

Inline scripts need the nonce of the request: `<script nonce="<?= csp_nonce() ?>">…</script>` (it is added to `script-src`). Event handler attributes (`onclick="…"`) are blocked. The panel has its own, stricter policy (it can't be framed). Static sites built with `static:build` don't get the header, their web server has to send it.
