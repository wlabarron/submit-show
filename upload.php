<?php
use Flow\Basic;
use Flow\Config;
use Flow\Request;
use submitShow\Database;
use submitShow\Recording;

require_once 'processing/requireAuth.php';
require_once 'vendor/autoload.php';
require_once 'processing/Database.php';
require_once 'processing/Recording.php';
require_once 'processing/Input.php';
$config = require 'processing/config.php';

$database  = new Database();
$recording = new Recording();

// prepare the input from the form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name      = Input::sanitise($_POST["name"]);
    $presenter = Input::sanitise($_POST["presenter"]);
    $date      = Input::sanitise($_POST["date"]);
} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $name      = Input::sanitise($_GET["name"]);
    $presenter = Input::sanitise($_GET["presenter"]);
    $date      = Input::sanitise($_GET["date"]);
} else {
    // Invalid request type.
    http_response_code(406);
    exit();
}

try {
    $recording->setName($name);
    $recording->setPresenter($presenter);
    $recording->setStart($date);
} catch (Exception $exception) {
    error_log("Invalid show metadata: " . $exception->getMessage());
    http_response_code(406);
    exit;
}

$flowConfig = new Config();
$flowConfig->setTempDir($config["tempDirectory"]);
$request = new Request();

try {
    $recording->setFileExtension($request->getFileName());
    $extension = $recording->getExtension();
} catch (Exception $exception) {
    error_log($exception->getMessage());
    http_response_code(500);
    exit;
}

if ($extension !== "mp3" &&
    $extension !== "m4a" &&
    $extension !== "mp4" &&
    $extension !== "aac") {
    // cancel upload
    http_response_code(406);
    exit;
} else if ($request->getTotalSize() > $config["maxShowFileSize"]) { // if the file is too large
    // cancel upload
    http_response_code(406);
    exit;
}

try {
    $fileName = $recording->getFileName();
} catch (Exception $exception) {
    error_log($exception->getMessage());
    http_response_code(500);
    exit;
}

// set the path to upload to
$uploadPath = $config["holdingDirectory"] . "/" . $fileName;

if (Basic::save($uploadPath, $flowConfig, $request)) {
    $removingMetadataLocation = $config["holdingDirectory"] . "/meta-" . $fileName;

    // Remove metadata from uploaded file, put in the show presenter and title instead
    // $metadata[0] is presenter, [1] is title, [2] is file extension
    // TODO Use info from Recording object instead
    $metadata = preg_split("/[-.]/", $fileName, 3);
    shell_exec("ffmpeg -i \"$uploadPath\" -map_metadata -1 -metadata title=\"$metadata[1]\" -metadata artist=\"$metadata[0]\" -c:v copy -c:a copy \"$removingMetadataLocation\"");

    // move metadata-removed file back to the upload path
    rename($removingMetadataLocation, $uploadPath);

    // Log upload completed
    try {
        if (!empty($_SESSION['samlNameId']))
            $database->log($_SESSION['samlNameId'], "upload", $fileName);
    } catch (Exception $e) {
        error_log("Failed to log file upload action from " . $_SESSION["samlNameId"] . ".");
    }
} else {
    // This is not a final chunk or request is invalid, continue to upload.
}

