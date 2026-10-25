---
title: Config options
---
See [Configuration guide](../../01_guides/04_configuration/page.md) for how config files are merged.

| Option | Default | |
|---|---|---|
| `debug` | `false` | shows errors with stack traces |
| `panel` | `true` | enables the [panel](../../01_guides/06_panel/page.md) at `/panel` |
| `contentFolder` | `"content"` | relative to the project dir or absolute |
| `siteFolder` | `"site"` | relative to the project dir or absolute |

Custom options are allowed: `App::instance()->config->get("my.option")`.
