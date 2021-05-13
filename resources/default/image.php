<?php

header('Cache-Control: max-age=0, private, no-cache');

$database = new submitShow\Database();

if (isset($_GET["show"]) && !empty($_GET["show"])) {
    $_GET["show"] = Input::sanitise($_GET["show"]);

    try {
        $image = imagecreatefromstring($database->getDefaultImage($_GET["show"]));
        if ($image !== false) {
            // output the image with the right header
            header('Content-Type: image/png');
            imagepng($image, null, 9);
            imagedestroy($image);
        } else {
            error_log("Failed to create default image from string in database.");
        }
    } catch (Exception $e) {
        error_log("An exception was thrown while getting default show image: " . $e->getMessage());
        http_response_code(500);
    }
} else {
    echo "";
}