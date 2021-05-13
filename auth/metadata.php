<?php

/**
 *  SP Metadata Endpoint
 *  Modified from https://github.com/onelogin/php-saml/tree/master/endpoints.
 */

use OneLogin\Saml2\Auth;

require(dirname(__DIR__) . '/vendor/autoload.php');
$config = require (dirname(__DIR__) . '/processing/config.php');

try {
    $auth = new Auth($config["samlSettings"]);
    $settings = $auth->getSettings();
    $metadata = $settings->getSPMetadata();
    $errors = $settings->validateMetadata($metadata);
    if (empty($errors)) {
        header('Content-Type: text/xml');
        echo $metadata;
    } else {
        throw new OneLogin_Saml2_Error(
            'Invalid SP metadata: ' . implode(', ', $errors),
            OneLogin_Saml2_Error::METADATA_SP_INVALID
        );
    }
} catch (Exception $e) {
    echo $e->getMessage();
}