<?php

// Project specific defaults, config/config.php (and config/config.{hostname}.php) override these.
return [
    // the docs load Fuse.js, speed-highlight and its theme and the Fira Code font from jsDelivr, the Inter font from rsms.me
    "csp" => [
        "script-src" => "'self' https://cdn.jsdelivr.net",
        "style-src" => "'self' 'unsafe-inline' https://cdn.jsdelivr.net https://rsms.me",
    ],
];
