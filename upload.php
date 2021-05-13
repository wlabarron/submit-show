<?php
use Flow\Basic;
use Flow\Config;
use Flow\Request;
use submitShow\Database;

require_once 'processing/requireAuth.php';
$config = require 'processing/config.php';
require_once 'vendor/autoload.php';

$database = new Database();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    header("HTTP/1.0 406 Not Acceptable", true, 406);
    exit();
}

if ( // if show name or broadcast date is missing, stop the upload
    ($_SERVER['REQUEST_METHOD'] === 'POST' &&
        (is_null($_POST["showName"]) || is_null($_POST["broadcastDate"]))) ||
    ($_SERVER['REQUEST_METHOD'] === 'GET' &&
        (is_null($_GET["showName"]) || is_null($_GET["broadcastDate"])))) {
    header("HTTP/1.0 406 Not Acceptable", true, 406);
    exit;
} else {
    // prepare the input from the form
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $showID               = Input::sanitise($_POST["showName"]);
        $specialShowName      = Input::sanitise($_POST["specialShowName"]);
        $specialShowPresenter = Input::sanitise($_POST["specialShowPresenter"]);
        $broadcastDate        = Input::sanitise($_POST["broadcastDate"]);
    } else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $showID               = Input::sanitise($_GET["showName"]);
        $specialShowName      = Input::sanitise($_GET["specialShowName"]);
        $specialShowPresenter = Input::sanitise($_GET["specialShowPresenter"]);
        $broadcastDate        = Input::sanitise($_GET["broadcastDate"]);
    }

    // check length of submitted data
    if (strlen($specialShowName) > 50 ||
        strlen($specialShowPresenter) > 50 ||
        strlen($broadcastDate) > 30) {
        header("HTTP/1.0 406 Not Acceptable", true, 406);
        exit;
    }
}

$flowConfig = new Config();
$flowConfig->setTempDir($config["tempDirectory"]);
$request = new Request();

// if the file name doesn't have the expected type of extension
$fileNameSplit = explode(".", $request->getFileName());
if (end($fileNameSplit) !== "mp3" && end($fileNameSplit) !== "m4a" && end($fileNameSplit) !== "mp4" && end($fileNameSplit) !== "aac") {
    // cancel upload
    header("HTTP/1.0 406 Not Acceptable", true, 406);
    exit;
} else if ($request->getTotalSize() > $config["maxShowFileSize"]) { // if the file is too large
    // cancel upload
    header("HTTP/1.0 406 Not Acceptable", true, 406);
    exit;
} else {
    // name the file in the correct format
    $uploadFileName = prepareFileName($showID, $request->getFileName(), $broadcastDate, $specialShowName, $specialShowPresenter);
}

// set the path to upload to
$uploadPath = $config["holdingDirectory"] . "/" . $uploadFileName;

if (Basic::save($uploadPath, $flowConfig, $request)) {
    $removingMetadataLocation = $config["holdingDirectory"] . "/meta-" . $uploadFileName;

    // Remove metadata from uploaded file, put in the show presenter and title instead
    // $metadata[0] is presenter, [1] is title, [2] is file extension
    $metadata = preg_split("/[-.]/", $uploadFileName, 3);
    shell_exec("ffmpeg -i \"$uploadPath\" -map_metadata -1 -metadata title=\"$metadata[1]\" -metadata artist=\"$metadata[0]\" -c:v copy -c:a copy \"$removingMetadataLocation\"");

    // move metadata-removed file back to the upload path
    rename($removingMetadataLocation, $uploadPath);

    // Log upload completed
    try {
        $database->log($_SESSION["samlNameId"], "upload", $uploadFileName);
    } catch (Exception $e) {
        error_log("Failed to log file upload action from " . $_SESSION["samlNameId"] . ".");
    }
} else {
    // This is not a final chunk or request is invalid, continue to upload.
}

