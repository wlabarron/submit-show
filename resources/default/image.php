<?php

header('Cache-Control: max-age=0, private, no-cache');

require __DIR__ . "/../../processing/Database.php";

$database = new submitShow\Database();

if (isset($_GET["show"]) && !empty($_GET["show"]) && is_numeric($_GET["show"])) {
    try {
        $imageString = $database->getDefaultImage($_GET["show"]);

        if (empty($imageString)) {
            http_response_code(404);
        } else {
            $image = imagecreatefromstring($imageString);
            if ($image !== false) {
                // output the image with the right header
                header('Content-Type: image/png');
                imagepng($image, null, 9);
                imagedestroy($image);
            } else {
                http_response_code(500);
                error_log("Failed to create default image from string in database.");
            }
        }
    } catch (Exception $e) {
        error_log("An exception was thrown while getting default show image: " . $e->getMessage());
        http_response_code(500);
    }
} else {
    http_response_code(400);
    echo "";
}