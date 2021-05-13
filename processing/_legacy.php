<?php

/**
 * Checks if the uploaded image meets the given criteria, and if so, returns the image as a blob.
 * @param $fileUpload object The image file upload object POSTed to the server (like {@code $_FILES["image"]}).
 * @throws Exception If the image fails validation.
 */
function handleImageUpload(object $fileUpload) {
    // If they actually have uploaded an image
    if (!empty($fileUpload["name"])) {
        // Get and check the image's file type
        $fileType = pathinfo($fileUpload["name"], PATHINFO_EXTENSION);
        $allowTypes = array('jpg', 'png', 'jpeg');
        if (!in_array(strtolower($fileType), $allowTypes)) { // TODO better image type checking
            throw new Exception("Uploaded image format not permitted.");
        }

        $config = require 'config.php';

        // Check the image's size
        if ($fileUpload["size"] > $config["maxShowImageSize"]) {
            throw new Exception("Uploaded image too large.");
        }

        // Return the image data as a blob
        $this->image = file_get_contents($fileUpload['tmp_name']);
    }
}