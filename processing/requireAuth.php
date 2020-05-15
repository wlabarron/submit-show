<?php

use SimpleSAML\Auth\Simple;

$config = require 'config.php';

// if simpleSAMLphp is set up, require authentication
if (!empty($config["simpleSAMLphpAutoloadPath"])) {
    require_once($config["simpleSAMLphpAutoloadPath"]);
    $as = new Simple($config["simpleSAMLphpAuthSource"]);
    $as->requireAuth();
    return $as->getAttributes();
} else {
    return null;
}