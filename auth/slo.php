<?php
/**
 *  SP Single Logout Service Endpoint
 *  Modified from https://github.com/onelogin/php-saml/tree/master/endpoints.
 */

use OneLogin\Saml2\Auth;

session_start();

require(dirname(__DIR__) . '/vendor/autoload.php');
$config = require (dirname(__DIR__) . '/processing/config.php');

try {
    $auth = new Auth($config["samlSettings"]);
    $auth->processSLO();
} catch (Exception $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit;
}


$errors = $auth->getErrors();

if (empty($errors)) {
    echo 'Successfully logged out';
} else {
    echo implode(', ', $errors);
}