<?php

use Blueprint\User;

return [
    "command" => "content:uids",
    "description" => "adds a uid to all pages, files and users without one",
    "run" => function () {
        $count = 0;
        foreach (site()->index() as $page) {
            if ($page->uid() === null && $page->isEditable()) {
                $page->update([]);
                $count++;
            }
            foreach ($page->files() as $file) {
                if ($file->uid() === null) {
                    $file->update([]);
                    $count++;
                }
            }
        }
        foreach (User::all() as $user) {
            if ($user->uid() === "") {
                $user->update([]);
                $count++;
            }
        }
        echo "Added $count uids", PHP_EOL;
    },
];
