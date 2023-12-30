<?php

use submitShow\Extraction;

require_once '../processing/Input.php';
require_once '../processing/Extraction.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET["start"]) && !empty($_GET["duration"]) && !empty($_GET["file"])) {
    $start    = Input::sanitise($_GET["start"]);
    $duration = Input::sanitise($_GET["duration"]);
    $file     = Input::sanitise($_GET["file"]);
    
    if (preg_match("/[0-9a-fA-F]+\.[0-9a-zA-Z]+/", $file) !== 1 || !file_exists($config["tempDirectory"] . "/" . $file)) {
        // File name is not a single file name, contains invalid characters, or otherwise doesn't exist.
        http_response_code(406);
        exit();
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Missing parameters.
    http_response_code(400);
    exit();
} else {
    // Invalid request type.
    http_response_code(406);
    exit();
}

$extraction = new Extraction();
$trimmedFile = $extraction->trim($start, $duration, $file);

if (empty($trimmedFile)) {
    http_response_code(500);
} else {
    echo($trimmedFile);
}