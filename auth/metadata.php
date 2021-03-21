<?php

/**
 *  SP Metadata Endpoint
 */

use OneLogin\Saml2\Auth;

require(dirname(__DIR__) . '/vendor/autoload.php');
require_once('settings.php');

try {
    $auth = new Auth($samlSettings);
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