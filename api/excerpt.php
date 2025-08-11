<?php

@ini_set('memory_limit','512M');

require_once '../processing/Input.php';

$config = require '../processing/config.php';

if ($_SERVER['REQUEST_METHOD'] === "GET" && !empty($_GET["file"]) && !empty($_GET["part"])) {
    $file = Input::sanitise($_GET["file"]);
    $part = Input::sanitise($_GET["part"]);
    
    // Using `basename` should remove any attempts at path traversal from the user input by only taking
    // the file name from the end of the provided string. We then use `realpath` to get the canonical
    // form of the path, again removing any funny-business.
    $filePath  = realpath($config["serverRecordings"]["recordingsDirectory"] . "/" . basename($file));
    
    // Before loading the file, double-check we're in the directory we expect (or deeper)
    if (strpos($filePath, $config["serverRecordings"]["recordingsDirectory"]) === 0) {
        header("Content-Disposition: inline");
        header("Content-Transfer-Encoding: binary"); 
        header("Content-Type: audio/*"); 
        passthru("ffmpeg -y -hide_banner -loglevel error  -i \"$filePath\" -ss 0 -t \"{$config["serverRecordings"]["auditionTime"]}\" -c copy -f mp3 -");
        exit;
    } else {
        http_response_code(403);
    }
}

// Else invalid request type.
http_response_code(406);