<?php

use OneLogin\Saml2\Auth;

$config = require 'config.php';

// if simpleSAMLphp is set up, require authentication
if (($config["saml"])) {
    session_start();

    require_once __DIR__ . '/../vendor/autoload.php';
    require_once __DIR__ . '/../auth/settings.php';
    $auth = new Auth($samlSettings);

    // If not signed in, sign in
    if (!isset($_SESSION['samlNameId'])) $auth->login();
} else {
    return null;
}