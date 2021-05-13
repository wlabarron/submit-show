<?php

use OneLogin\Saml2\Auth;

$config = require 'config.php';

// if simpleSAMLphp is set up, require authentication
if (($config["samlEnabled"])) {
    session_start();

    require(dirname(__DIR__) . '/vendor/autoload.php');
    $config = require __DIR__ . '/config.php';
    $auth = new Auth($config["samlSettings"]);

    // If not signed in, sign in
    if (!isset($_SESSION['samlNameId'])) $auth->login();
} else {
    return null;
}