<?php

use SimpleSAML\Auth\Simple;

$config = require 'config.php';

// if simpleSAMLphp is set up, require authentication
if (!empty($config["simpleSAMLphpAutoloadPath"])) {
    require_once($config["simpleSAMLphpAutoloadPath"]);
    $as = new Simple($config["simpleSAMLphpAuthSource"]);
    $as->requireAuth();
    $attributes = $as->getAttributes();
}

$connections = require 'databaseConnections.php';
require 'usefulFunctions.php';

// Set up query for image
$savedImageQuery = $connections["submissions"]->prepare("SELECT image FROM saved_info WHERE `show` = ?");

// Sanitise requested show
$_GET["show"] = clearUpInput($_GET["show"]);

// If the request is a number as expected
if (is_numeric($_GET["show"])) {
    // Set up the SQL query
    $savedImageQuery->bind_param("i", $_GET["show"]);

    // Do query, handle errors
    if (!$savedImageQuery->execute()) {
        error_log($savedImageQuery->error);
        // TODO Report this?
    }
    $imageString = mysqli_fetch_assoc(mysqli_stmt_get_result($savedImageQuery));

    // Get image from the string in the database
    $image = imagecreatefromstring($imageString["image"]);
    if ($image !== false) {
        // output the image with the right header
        header('Content-Type: image/png');
        imagepng($image, null, 9);
        imagedestroy($image);
    } else {
        // TODO failed
    }
}