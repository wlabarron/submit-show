<?php

/**
 * Prompts a user to log in, if they're not already logged in. {@code require} this file at the top of any scripts
 * which should prompt for login.
 */

use OneLogin\Saml2\Auth;

$config = require 'config.php';

if (($config["samlEnabled"])) {
    if (session_status() === PHP_SESSION_NONE) session_start();

    require(dirname(__DIR__) . '/vendor/autoload.php');
    $config = require __DIR__ . '/config.php';

    try {
        $auth = new Auth($config["samlSettings"]);

        // If not signed in, sign in
        if (!isset($_SESSION['samlNameId'])) $auth->login();
    } catch (Exception $e) {
        error_log($e->getMessage());
    }
}