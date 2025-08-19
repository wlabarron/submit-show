<?php 
require __DIR__ . '/../scripts/post-only.php';
require __DIR__ . '/../scripts/promptLogin.php';
$config = require __DIR__ . '/../config.php';

if (!$config["serverRecordings"]["enabled"]) { 
    error_log("Server recording not enabled");
    http_response_code(403);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require __DIR__ . "/../components/head.html"; ?>
</head>
<body>
<?php require __DIR__ . "/../components/noscript.html"; ?>

<div class="container">
    <h1 class="h3">Select your show</h1>
    <form id="form" method="POST" action="select-marker.php">
        <?php require __DIR__ . '/../components/return-to-sender.php'; ?>
        
        <p>Which recording would you like to trim and submit?</p>
        <div class="list-group">
        <?php
            $recordings = array_diff(scandir($config["serverRecordings"]["recordingsDirectory"], SCANDIR_SORT_DESCENDING), array('..', '.'));
            foreach ($recordings as $recording) {
                echo '<button type="submit" class="list-group-item list-group-item-action" name="fileName" value="' . $recording . '">
                        ' . $recording . '
                    </button>';
            }
        ?>
        </div>
    </form>
</body>
</html>
