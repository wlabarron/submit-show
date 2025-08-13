<?php

@ini_set('memory_limit','512M');

require_once '../processing/Input.php';

$config = require '../processing/config.php';

if ($_SERVER['REQUEST_METHOD'] === "GET" && !empty($_GET["file"]) && !empty($_GET["part"])) {
    $part = Input::sanitise($_GET["part"]);
    
    try {
        $filePath  = Input::fileNameToPath($_GET["file"]);
    } catch (Exception $exception) {
        error_log("Couldn't create sanitised file path for selected show: " . $exception->getMessage());
        http_response_code(404);
        exit;
    }
    
    
    // Before loading the file, double-check we're in the directory we expect (or deeper)
    if (strpos($filePath, $config["serverRecordings"]["recordingsDirectory"]) === 0) {
        header("Content-Disposition: inline");
        header("Content-Transfer-Encoding: binary"); 
        header("Content-Type: " . mime_content_type($filePath));
        if ($_GET["part"] === "start") {
            passthru("ffmpeg -y -hide_banner -loglevel error -accurate_seek -ss 0 -i \"$filePath\" -t \"{$config["serverRecordings"]["auditionTime"]}\" -c copy -f mp3 -");
        } else if ($_GET["part"] === "end") {
            passthru("ffmpeg -y -hide_banner -loglevel error -accurate_seek -sseof \"-{$config["serverRecordings"]["auditionTime"]}\" -i \"$filePath\" -t \"{$config["serverRecordings"]["auditionTime"]}\" -c copy -f mp3 -");
        } else {
            http_response_code(400);
        }
        exit;
    } else {
        http_response_code(403);
    }
}

// Else invalid request type.
http_response_code(406);