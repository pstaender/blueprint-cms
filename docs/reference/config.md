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

Custom options are allowed: `App::instance()->config->get("my.option")`.
