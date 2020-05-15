<?php
$config = require './processing/config.php';

// if simpleSAMLphp is set up, require authentication
if (!empty($config["simpleSAMLphpAutoloadPath"])) {
    require_once($config["simpleSAMLphpAutoloadPath"]);
    $as = new Simple($config["simpleSAMLphpAuthSource"]);
    $as->requireAuth();
    $attributes = $as->getAttributes();
}