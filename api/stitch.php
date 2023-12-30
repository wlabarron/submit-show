<?php

use submitShow\Extraction;
use submitShow\Recording;

require_once '../processing/Input.php';
require_once '../processing/Extraction.php';
require_once '../processing/Recording.php';
$config = require '../processing/config.php';

$extraction = new Extraction();

if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET["from"]) && !empty($_GET["to"])) {
    $from = Input::sanitise($_GET["from"]);
    $to   = Input::sanitise($_GET["to"]);
    
    $stitchedFile = $extraction->stitch($from, $to, $config["tempDirectory"] . "/" . uniqid(), false);
    
    if (empty($stitchedFile)) {
        http_response_code(500);
    } else {
        header('Cache-Control: max-age=0, private, no-cache');
        header('Content-Type: ' . mime_content_type($stitchedFile));
        readfile($stitchedFile);
    }
    exit();
} else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!empty($data["from"]) && !empty($data["to"]) && !empty($data["name"]) && !empty($data["presenter"]) && !empty($data["date"])) {
        $from      = Input::sanitise($data["from"]);
        $to        = Input::sanitise($data["to"]);
        $name      = Input::sanitise($data["name"]);
        $presenter = Input::sanitise($data["presenter"]);
        $date      = Input::sanitise($data["date"]);
        
        $recording = new Recording();
        
        $recording->setName($name);
        $recording->setPresenter($presenter);
        $recording->setStart($date);
        
        $stitchedFile = $extraction->stitch($from, $to, $config["holdingDirectory"] . "/" . $recording->getFileName(false), true);
        
        if (empty($stitchedFile)) {
            http_response_code(500);
        } else {
            header('Cache-Control: max-age=0, private, no-cache');
            $recording->setFileExtension($stitchedFile);
            echo($recording->getFileName());
        }
        
        exit();   
    }
}

// Else invalid request type.
http_response_code(406);

