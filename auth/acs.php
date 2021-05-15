<?php

/**
 *  SP Assertion Consumer Service Endpoint
 *  Modified from https://github.com/onelogin/php-saml/tree/master/endpoints.
 */

use OneLogin\Saml2\Auth;
use OneLogin\Saml2\Utils;

session_start();

require(dirname(__DIR__) . '/vendor/autoload.php');
$config = require (dirname(__DIR__) . '/processing/config.php');

if (isset($_SESSION) && isset($_SESSION['AuthNRequestID'])) {
    $requestID = $_SESSION['AuthNRequestID'];
} else {
    $requestID = null;
}

try {
    $auth   = new Auth($config["samlSettings"]);
    $auth->processResponse($requestID);
} catch (Exception $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit;
}

$errors = $auth->getErrors();

if (!empty($errors)) {
    echo '<p>Error occurred.</p>';
    error_log(implode(', ', $errors));
    error_log($auth->getLastErrorReason());
}

if (!$auth->isAuthenticated()) {
    echo "<p>Not authenticated</p>";
    exit();
}

$_SESSION['samlUserdata']              = $auth->getAttributes();
$_SESSION['samlNameId']                = $auth->getNameId();
$_SESSION['samlNameIdFormat']          = $auth->getNameIdFormat();
$_SESSION['samlNameIdNameQualifier']   = $auth->getNameIdNameQualifier();
$_SESSION['samlNameIdSPNameQualifier'] = $auth->getNameIdSPNameQualifier();
$_SESSION['samlSessionIndex']          = $auth->getSessionIndex();
unset($_SESSION['AuthNRequestID']);
if (isset($_POST['RelayState']) && Utils::getSelfURL() != $_POST['RelayState']) {
    $auth->redirectTo($_POST['RelayState']);
}