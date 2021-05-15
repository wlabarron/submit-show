<?php

/**
 * {@code require} this file at the top of the script. If the user isn't logged in, a 403 Forbidden message is returned
 * and execution is stopped.
 */

use OneLogin\Saml2\Auth;

$config = require 'config.php';

if (($config["samlEnabled"])) {
    if (session_status() === PHP_SESSION_NONE) session_start();

    require(dirname(__DIR__) . '/vendor/autoload.php');
    $config = require __DIR__ . '/config.php';

    try {
        $auth = new Auth($config["samlSettings"]);

        // If not signed in, reject
        if (!isset($_SESSION['samlNameId'])) {
            http_response_code(403);
            exit;
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
    }
}