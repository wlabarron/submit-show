<?php
@ini_set('memory_limit','512M');

use submitShow\Recording;

require_once __DIR__ . '/requireAuth.php';
require_once __DIR__ . '/../classes/Recording.php';
require_once __DIR__ . '/../classes/Input.php';
require_once __DIR__ . '/../classes/Storage.php';
$config = require __DIR__ . '/../config.php';

$recording = new Recording();

// prepare the input from the form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name           = Input::sanitise($_POST["name"]);
    $presenter      = Input::sanitise($_POST["presenter"]);
    $filePath       = Input::fileNameToPath($_POST["fileName"]);
    $startTimestamp = Input::sanitise($_POST["startTimestamp"]);
    $endTimestamp   = Input::sanitise($_POST["endTimestamp"]);
} else {
    // Invalid request type.
    http_response_code(406);
    exit();
}

if (empty($startTimestamp) || empty($endTimestamp)) {
    error_log("Start or end timestamp was empty");
    http_response_code(406);
    exit;
}

if (!is_numeric($startTimestamp) || !is_numeric($endTimestamp)) {
    error_log("Start or end timestamp was not numeric");
    http_response_code(406);
    exit;
}

try {
    $recording->setName($name);
    $recording->setPresenter($presenter);
    $recording->setStart(substr(basename($filePath), 0, 10)); // this should work because file names should start with YYYY-MM-DD. Should.
} catch (Exception $exception) {
    error_log("Invalid show metadata: " . $exception->getMessage());
    http_response_code(406);
    exit;
}

try {
    $recording->setFileExtension($filePath);
    $formattedFileName = $recording->getFileName();
} catch (Exception $exception) {
    error_log($exception->getMessage());
    http_response_code(500);
    exit;
}

// Work out where this file will be 'held' after it's been trimmed and before submission, then check the necessary directories exist
$holdingPath = $config["holdingDirectory"] . "/" . $formattedFileName;
if (!Storage::createParentDirectories($holdingPath)) {
    error_log("Failed to create parent directories for  " . $holdingPath);
    http_response_code(500);
    exit;
}

$shellOutput;
$shellResult;

// Trim the file and add metadata, moving it to the holding path in the process
exec("ffmpeg -y -accurate_seek -ss \"$startTimestamp\" -to \"$endTimestamp\" -i \"$filePath\" -map_metadata -1 -metadata title=\"" . $recording->getName() . " " . $recording->get6DigitStartDate() . "\" -metadata artist=\"" . $recording->getPresenter() . "\" -c copy \"$holdingPath\"", $shellOutput, $shellResult);

if ($shellResult !== 0) {
    error_log("ffmpeg trimming & metadata writing failed");
    http_response_code(500);
    exit;
}
