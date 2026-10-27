<?php
// The endpoint of the "Was this page helpful?" form of the docs layout, see site/models/PageRating.php.
// page.json hides it in the menu.

if (($_SERVER["REQUEST_METHOD"] ?? "GET") !== "POST") {
    echo json_encode(["error" => "Ratings are sent with POST"]);
    return;
}

$rated = site()->find((string) ($_POST["page"] ?? ""), includeDrafts: false);

if ($rated === null || !in_array($_POST["ratingValue"] ?? null, ["1", "-1"], true)) {
    echo json_encode(["error" => "Invalid rating"]);
    return;
}

$rating = PageRating::rate($rated, (int) $_POST["ratingValue"], (string) ($_SERVER["REMOTE_ADDR"] ?? ""));
echo json_encode($rating === null ? ["error" => "You have already rated this page"] : ["ok" => true]);
