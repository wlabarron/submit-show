<?php

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;

require_once __DIR__ . '/../vendor/autoload.php';
$config = require "config.php";

// Connect to database, set charset and return error if connection failed.
$detailsConnection = new mysqli($config["detailsServer"], $config["detailsUser"], $config["detailsPassword"], $config["detailsDatabaseName"]);
$detailsConnection->query("SET NAMES 'utf8'");
if ($detailsConnection->connect_error) {
    error_log($detailsConnection->connect_error);
    die("Something's gone wrong. Please try again in a few minutes.");
}

// Connect to database, set charset and return error if connection failed.
$submissionsConnection = new mysqli($config["submissionsServer"], $config["submissionsUser"], $config["submissionsPassword"], $config["submissionsDatabaseName"]);
$submissionsConnection->query("SET NAMES 'utf8'");
if ($submissionsConnection->connect_error) {
    error_log($submissionsConnection->connect_error);
    die("Something's gone wrong. Please try again in a few minutes.");
}

// if S3 is configured
if (!empty($config["s3Endpoint"])) {
    try {
        // create S3 client
        $s3Client = new S3Client([
            'endpoint' => $config["s3Endpoint"],
            'region' => $config["s3Region"],
            'version' => 'latest',
            'credentials' => [
                'key' => $config["s3AccessKey"],
                'secret' => $config["s3Secret"],
            ],
        ]);
    } catch (S3Exception $e) {
        error_log("Couldn't create S3 client. Error:\n" . $e->getMessage());
    }
} else {
    $s3Client = null;
}

return array(
    "details" => $detailsConnection,
    "submissions" => $submissionsConnection,
    "s3" => $s3Client
);
