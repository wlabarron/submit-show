<?php
/**
 *  SP Single Logout Service Endpoint
 *  Modified from https://github.com/onelogin/php-saml/tree/master/endpoints.
 */

use OneLogin\Saml2\Auth;

session_start();

require(__DIR__ . '/../../vendor/autoload.php');
$config = require(__DIR__ . '/../../config.php');

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