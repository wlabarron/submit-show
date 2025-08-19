<?php

/**
 *  SP Metadata Endpoint
 *  Modified from https://github.com/onelogin/php-saml/tree/master/endpoints.
 */

use OneLogin\Saml2\Auth;

require(__DIR__ . '/../../vendor/autoload.php');
$config = require(__DIR__ . '/../../config.php');

try {
    $auth = new Auth($config["samlSettings"]);
    $settings = $auth->getSettings();
    $metadata = $settings->getSPMetadata();
    $errors   = $settings->validateMetadata($metadata);
    if (empty($errors)) {
        header('Content-Type: text/xml');
        echo $metadata;
    } else {
        error_log('Invalid SP metadata: ' . implode(', ', $errors));
    }
} catch (Exception $e) {
    error_log($e->getMessage());
}