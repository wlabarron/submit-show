<?php
/**
 *  SP Single Logout Service Endpoint
 *  Modified from https://github.com/onelogin/php-saml/tree/master/endpoints.
 */

use OneLogin\Saml2\Auth;

session_start();

require(dirname(__DIR__) . '/vendor/autoload.php');
require_once('settings.php');
$auth = new Auth($samlSettings);

$auth->processSLO();

$errors = $auth->getErrors();

if (empty($errors)) {
    echo 'Successfully logged out';
} else {
    echo implode(', ', $errors);
}