<?php
require_once 'requireAuth.php';

$config = require 'config.php';

$connections = require 'databaseConnections.php';
require 'usefulFunctions.php';

// Prepare SQL queries
$savedShowDetailsQuery = $connections["submissions"]->prepare("SELECT * FROM saved_info WHERE `show` = ?");
$savedTagsQuery = $connections["submissions"]->prepare("SELECT tag FROM saved_tags WHERE `show` = ? ORDER BY id");

// Sanitise requested show
$_GET["show"] = clearUpInput($_GET["show"]);

// If the request is a number as expected
if (is_numeric($_GET["show"])) {
    // Set up the SQL queries
    $savedShowDetailsQuery->bind_param("i", $_GET["show"]);
    $savedTagsQuery->bind_param("i", $_GET["show"]);

    // Do query, handle errors
    if (!$savedShowDetailsQuery->execute()) {
        // TODO reporting
        logWithLevel("error", "Failed to get details of saved show to return to user. " . $savedShowDetailsQuery->error);
    }
    $showDetails = mysqli_fetch_assoc(mysqli_stmt_get_result($savedShowDetailsQuery));

    // Do query, handle errors
    if (!$savedTagsQuery->execute()) {
        logWithLevel("error", "Failed to get tags of saved show to return to user. " . $savedTagsQuery->error);
    }
    $tags = mysqli_fetch_all(mysqli_stmt_get_result($savedTagsQuery));

    // If an image exists, mark as such
    if (!empty($showDetails["image"])) {
        $imageExists = true;
    } else {
        $imageExists = false;
    }

    if (!empty($showDetails["description"])) {
        $description = $showDetails["description"];
    } else {
        $description = null;
    }

    // Set up a JSON object to return
    echo json_encode(array(
        "description" => $description,
        "imageExists" => $imageExists,
        "tags" => $tags
    ));
}