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
$auth = new Auth($config["samlSettings"]);

if (isset($_SESSION) && isset($_SESSION['AuthNRequestID'])) {
    $requestID = $_SESSION['AuthNRequestID'];
} else {
    $requestID = null;
}

$auth->processResponse($requestID);

$errors = $auth->getErrors();

if (!empty($errors)) {
    echo '<p>', implode(', ', $errors), '</p>';
    if ($auth->getSettings()->isDebugActive()) {
        echo '<p>' . $auth->getLastErrorReason() . '</p>';
    }
}

if (!$auth->isAuthenticated()) {
    echo "<p>Not authenticated</p>";
    exit();
}

$_SESSION['samlUserdata'] = $auth->getAttributes();
$_SESSION['samlNameId'] = $auth->getNameId();
$_SESSION['samlNameIdFormat'] = $auth->getNameIdFormat();
$_SESSION['samlNameIdNameQualifier'] = $auth->getNameIdNameQualifier();
$_SESSION['samlNameIdSPNameQualifier'] = $auth->getNameIdSPNameQualifier();
$_SESSION['samlSessionIndex'] = $auth->getSessionIndex();
unset($_SESSION['AuthNRequestID']);
if (isset($_POST['RelayState']) && Utils::getSelfURL() != $_POST['RelayState']) {
    $auth->redirectTo($_POST['RelayState']);
}