<?php
$attributes = require_once 'requireAuth.php';

$config = require 'config.php';

use Flow\Basic;
use Flow\Config;
use Flow\Request;
use SimpleSAML\Auth\Simple;

require_once '../vendor/autoload.php';
require 'usefulFunctions.php';
$connections = require 'databaseConnections.php';

if ( // if show name or broadcast date is missing, stop the upload
    ($_SERVER['REQUEST_METHOD'] === 'POST' &&
        (is_null($_POST["showName"]) || is_null($_POST["broadcastDate"]))) ||
    ($_SERVER['REQUEST_METHOD'] === 'GET' &&
        (is_null($_GET["showName"]) || is_null($_GET["broadcastDate"])))) {
    header("HTTP/1.0 406 Not Acceptable", true, 406);
    logWithLevel("info", "File upload sent with missing form data.");
    exit;
} else {
    // prepare the input from the form
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $showID = clearUpInput($_POST["showName"]);
        $specialShowName = clearUpInput($_POST["specialShowName"]);
        $specialShowPresenter = clearUpInput($_POST["specialShowPresenter"]);
        $broadcastDate = clearUpInput($_POST["broadcastDate"]);
    } else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $showID = clearUpInput($_GET["showName"]);
        $specialShowName = clearUpInput($_GET["specialShowName"]);
        $specialShowPresenter = clearUpInput($_GET["specialShowPresenter"]);
        $broadcastDate = clearUpInput($_GET["broadcastDate"]);
    }

    // check length of submitted data
    if (strlen($specialShowName) > 50 ||
        strlen($specialShowPresenter) > 50 ||
        strlen($broadcastDate) > 30) {
        header("HTTP/1.0 406 Not Acceptable", true, 406);
        logWithLevel("info", "File upload sent with form data which is too long.");
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
    logWithLevel("info", "Invalid file type for show.");
    exit;
} else if ($request->getTotalSize() > $config["maxShowFileSize"]) { // if the file is too large
    // cancel upload
    header("HTTP/1.0 406 Not Acceptable", true, 406);
    logWithLevel("info", "Show file too large.");
    exit;
} else {
    // name the file in the correct format
    $uploadFileName = prepareFileName($showID, $request->getFileName(), $broadcastDate, $specialShowName, $specialShowPresenter);
}

// set the path to upload to
$uploadPath = $config["holdingDirectory"] . "/" . $uploadFileName;

if (Basic::save($uploadPath, $flowConfig, $request)) {
    logWithLevel("info", "Uploaded " . $uploadFileName);
    logToDatabase($attributes["identifier"][0], "upload", $uploadFileName);
} else {
    // This is not a final chunk or request is invalid, continue to upload.
}

