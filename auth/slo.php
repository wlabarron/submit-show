<?php
/**
 *  SP Single Logout Service Endpoint
 */

use OneLogin\Saml2\Auth;

session_start();

require(dirname(__DIR__) . '/vendor/autoload.php');
require_once('settings.php');
$auth = new Auth($samlSettings);

$auth->processSLO();

$errors = $auth->getErrors();

if (empty($errors)) {
    echo 'Sucessfully logged out';
} else {
    echo implode(', ', $errors);
}