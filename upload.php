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
require_once 'processing/Storage.php';
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
if (!Storage::createParentDirectories($uploadPath)) {
    error_log("Failed to create parent directories for  " . $uploadPath);
    http_response_code(500);
    exit;
}

// If this is the final chunk of the file
if (Basic::save($uploadPath, $flowConfig, $request)) {
    try {
        // Write metadata about the show into the file
        $removingMetadataLocation = $uploadPath . "-meta." . $extension;
        shell_exec("ffmpeg -i \"$uploadPath\" -map_metadata -1 -metadata title=\"" . $recording->getName() . " " . $recording->get6DigitStartDate() . "\" -metadata artist=\"" . $recording->getPresenter() . "\" -c:v copy -c:a copy \"$removingMetadataLocation\"");
        // move metadata-removed file back to the upload path
        rename($removingMetadataLocation, $uploadPath);
    } catch (Exception $e) {
        error_log($e->getMessage());
        http_response_code(500);
        exit;
    }

    // Log upload completed
    try {
        if (!empty($_SESSION['samlNameId']))
            $database->log($_SESSION['samlNameId'], "upload", $fileName);
    } catch (Exception $e) {
        error_log("Failed to log file upload action from " . $_SESSION["samlNameId"] . ".");
    }
}
