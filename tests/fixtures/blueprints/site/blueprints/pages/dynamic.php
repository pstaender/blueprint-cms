<?php

return [
    "title" => "Dynamic",
    "fields" => [
        "title" => ["type" => "text", "default" => fn() => "computed"],
    ],
];
