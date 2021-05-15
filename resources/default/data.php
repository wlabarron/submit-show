<?php

header('Cache-Control: max-age=0, private, no-cache');

require __DIR__ . "/../../processing/requireAuth.php";

require __DIR__ . "/../../processing/Database.php";

$database = new submitShow\Database();

if (isset($_GET["show"]) && !empty($_GET["show"]) && is_numeric($_GET["show"])) {
    try {
        $data = $database->getDefaults($_GET["show"]);

        if (empty($data)) {
            http_response_code(404);
        } else {
            echo json_encode($data);
        }
    } catch (Exception $e) {
        error_log("An exception was thrown while getting default show info: " . $e->getMessage());
        http_response_code(500);
    }
} else {
    http_response_code(400);
}

