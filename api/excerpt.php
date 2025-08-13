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
    
    header("Content-Disposition: inline");
    header("Content-Transfer-Encoding: binary"); 
    header("Content-Type: " . mime_content_type($filePath));
    
    if ($_GET["part"] === "end") {
        $fileDuration = intval(shell_exec("mediainfo --Output='General;%Duration%'  \"$filePath\"")) / 1000; // ms to sec
        $excerptStartTime = $fileDuration - $config["serverRecordings"]["auditionTime"];
    } else if ($_GET["part"] === "start") {
        $excerptStartTime = 0;
    } else {
        http_response_code(400);
        exit;
    }
    
    passthru("ffmpeg -y -hide_banner -loglevel error -accurate_seek -ss \"$excerptStartTime\" -i \"$filePath\" -t \"{$config["serverRecordings"]["auditionTime"]}\" -c copy -f mp3 -");
} else {
    // Else invalid request type.
    http_response_code(406);
}