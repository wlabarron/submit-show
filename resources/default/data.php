<?php

$database = new submitShow\Database();

if (isset($_GET["show"]) && !empty($_GET["show"])) {
    $_GET["show"] = Input::sanitise($_GET["show"]);

    try {
        echo json_encode($database->getDefaults($_GET["show"]));
    } catch (Exception $e) {
        error_log("An exception was thrown while getting default show info: " . $e->getMessage());
        http_response_code(500);
    }
} else {
    echo json_encode(array(
        "description" => "",
        "tags" => array()
    ));
}

