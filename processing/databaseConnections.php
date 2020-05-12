<?php
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

return array (
    "details" => $detailsConnection,
    "submissions" => $submissionsConnection
);
?>