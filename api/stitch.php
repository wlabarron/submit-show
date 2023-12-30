<?php

use submitShow\Extraction;

require_once '../processing/Input.php';
require_once '../processing/Extraction.php';
$config = require '../processing/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET["from"]) && !empty($_GET["to"])) {
    $from = Input::sanitise($_GET["from"]);
    $to   = Input::sanitise($_GET["to"]);
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
$stitchedFile = $extraction->stitch($from, $to);

if (empty($stitchedFile)) {
    http_response_code(500);
} else {
    header('Cache-Control: max-age=0, private, no-cache');
    header('Content-Type: ' . mime_content_type($config["tempDirectory"] . "/" . $stitchedFile));
    readfile($config["tempDirectory"] . "/" . $stitchedFile);
}