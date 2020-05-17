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
    error_log("File upload sent with missing form data.");
    exit;
} else {
    // prepare the input from the form
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $showName = clearUpInput($_POST["showName"]);
        $broadcastDate = clearUpInput($_POST["broadcastDate"]);
    } else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $showName = clearUpInput($_GET["showName"]);
        $broadcastDate = clearUpInput($_GET["broadcastDate"]);
    }

    // prepare a database query for show info
    $showDetailsQuery = $connections["details"]->prepare($config["oneShowQuery"]);
    $showDetailsQuery->bind_param("i", $showName);

    // get the info about the show
    $showDetailsQuery->execute();
    $showDetails = mysqli_fetch_assoc($showDetailsQuery->get_result());

    // prepare the passed date
    $date = date("ymd", strtotime($broadcastDate));
}

$flowConfig = new Config();
$flowConfig->setTempDir($config["tempDirectory"]);
$request = new Request();

// if the file name doesn't have the expected type of extension
$fileNameSplit = explode(".", $request->getFileName());
if (end($fileNameSplit) !== "mp3" && end($fileNameSplit) !== "m4a" && end($fileNameSplit) !== "mp4" && end($fileNameSplit) !== "aac") {
    // cancel upload
    header("HTTP/1.0 406 Not Acceptable", true, 406);
    error_log("Invalid file type sent.");
    exit;
} else {
    // name the file in the correct format
    $uploadFileName = $showDetails["presenter"] . "-" . $showDetails["name"] . " " . $date . "." . end($fileNameSplit); // The name the file will have on the server
}

// set the path to upload to
$uploadPath = $config["holdingDirectory"] . "/" . $uploadFileName;

if (Basic::save($uploadPath, $flowConfig, $request)) {
    error_log("Uploaded " . $uploadFileName);
    logToDatabase($attributes["identifier"][0], "upload", $uploadFileName);
} else {
    // This is not a final chunk or request is invalid, continue to upload.
}

