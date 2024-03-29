<?php

use Flow\FileOpenException;
use Flow\Uploader;
use submitShow\Database;

require __DIR__ . "/../vendor/autoload.php";
require __DIR__ . "/Database.php";
require __DIR__ . "/Email.php";
require __DIR__ . "/Link.php";
require __DIR__ . "/Storage.php";
require __DIR__ . "/Recording.php";
$config   = require 'config.php';

if (lock($config)) {
    try {
        $storage = Storage::getProvider();
    } catch (Exception $e) {
        error_log("Failed to get a storage provider instance.");
        unlock($config);
        exit();
    }
    $database = new Database();

    if (!empty($config["mixcloudAccessToken"])) {
        $showsDueToPublish = $database->getShowsForPublication();
        if ($showsDueToPublish) {
            foreach ($showsDueToPublish as $show) {
                try {
                    publishShow($show, $config, $storage, $database);
                } catch (Exception $e) {
                    error_log($e->getMessage());
                }
            }
        }
    }

    $showsDueToDelete = $database->getExpiredSubmissions();
    if ($showsDueToDelete) {
        foreach ($showsDueToDelete as $show) {
            try {
                deleteShow($show, $config, $storage, $database);
            } catch (Exception $e) {
                error_log($e->getMessage());
            }
        }
    }

    $showsToOffload = $database->getShowsToOffload();
    if ($showsToOffload) {
        foreach ($showsToOffload as $show) {
            try {
                offloadShow($show, $storage, $database);
            } catch (Exception $e) {
                error_log($e->getMessage());
            }
        }
    }

    // Get rid of leftover chunks and files from file uploads which never finished and forms which weren't submitted
    try {
        Uploader::pruneChunks($config["tempDirectory"]);
        Uploader::pruneChunks($config["holdingDirectory"]);
    } catch (FileOpenException $e) {
        error_log("Failed to prune upload remnants. Details:\n" . $e->getMessage());
    }

    unlock($config);
} else {
    error_log("Cron is already running at the moment. It can't be re-run until it's finished.");
}

/**
 * Used to prevent this script running in parallel.
 * @param array config The project config array.
 * @return bool {@code true} if a lock could be acquired, {@code false} if not. If return false, execution should stop.
 */
function lock($config): bool {
    return (mkdir($config["tempDirectory"] . '/showSubmissionsCronRunning.lock', 0700));
}

/**
 * Unlocks this script once execution is finished.
 * @param array config The project config array.
 */
function unlock($config) {
    rmdir($config["tempDirectory"] . '/showSubmissionsCronRunning.lock');
}

/**
 * Publishes the specified show to Mixcloud and marks it for deletion.
 * @param array $show Database record for the recording to publish.
 * @param array $config Project configuration array.
 * @param Storage $storage Instance of the appropriate storage controller.
 * @param Database $database Instance of the appropriate database connector.
 * @throws Exception
 */
function publishShow(array $show, array $config, Storage $storage, Database $database) {
    if ($show["file-location"] == Storage::$LOCATION_WAITING) {
        $path = $config["waitingDirectory"] . "/" . $show["file"];
    } else {
        $path = $storage->retrieve($show["file"]);
    }

    $postData = array(
        'mp3' => new CURLFile($path),
        'name' => htmlspecialchars_decode($show["title"], ENT_QUOTES),
        'description' => $show["description"]
    );

    // if there's an image
    if (!empty($show["image"])) {
        // turn the blob into a PNG
        $image = imagecreatefromstring($show["image"]);
        if ($image !== false) {
            imagepng($image, $config["tempDirectory"] . "/img.png");
            $imagePNG = new CURLFile($config["tempDirectory"] . "/img.png");
            imagedestroy($image);
            $postData['picture'] = $imagePNG;
        }
    }

    $tags = $database->getSubmissionTags($show["id"]);
    // add each tag to the POST data
    for ($i = 0; $i < sizeof($tags); $i++) {
        $postData["tags-" . $i . "-tag"] = $tags[$i];
    }

    // set up curl
    $curl = curl_init('https://api.mixcloud.com/upload/?access_token=' . $config["mixcloudAccessToken"]);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);

    // execute the curl POST
    $response = json_decode(curl_exec($curl), true);

    // close the request
    curl_close($curl);

    if (isset($response["result"]["success"]) && $response["result"]["success"]) {
        $database->markSubmissionForDeletion($show["id"]);

        if ($show["notification-email"]) {
            Email::send($show["notification-email"],
                $show["title"] . " published",
                "Hello!\n\n" .
                "\"" . $show["title"] . "\" was just published to Mixcloud. Here's the link: " . Link::shorten("https://www.mixcloud.com" . $response["result"]["key"]) . "\n\n" .
                "Thank you!\n\n" .
                "If you'd prefer not to receive these emails in future, leave the notification box unticked when you submit your show.");
        }
    } else {
        error_log("Failed to publish submission " . $show["id"] . " to Mixcloud. Response:\n" . json_encode($response));
    }

    // delete the temporary image file, if there was an image
    if (!empty($show["image"])) unlink($config["tempDirectory"] . "/img.png");

    // If we uploaded a copy of the file, delete it. The copy would be from if the file was uploaded after it has been
    // offloaded. If the file was in the waiting area, we'll have read it from there, and so have nothing to delete.
    if ($show["file-location"] != Storage::$LOCATION_WAITING) unlink($path);
}

/**
 * Deletes the specified submission's file in the storage provider and entry in the database.
 * @param array $show Database record for the recording to publish.
 * @param array $config Project configuration array.
 * @param Storage $storage Instance of the appropriate storage controller.
 * @param Database $database Instance of the appropriate database connector.
 * @throws Exception
 */
function deleteShow(array $show, array $config, Storage $storage, Database $database) {
    // If old files should be deleted
    if ($config["deleteStoredCopies"]) {
        if ($show["file-location"] == Storage::$LOCATION_WAITING) {
            if (!unlink($config["waitingDirectory"] . "/" . $show["file"]))
                throw new Exception("Couldn't delete file from waiting directory.");
        } else {
            $storage->delete($show["file"]);
        }
    }

    $database->deleteSubmission($show["id"]);
}

/**
 * Offloads the specified submission's file to the main storage location and updates the database accordingly.
 * @param array $show Database record for the recording to offload.
 * @param Storage $storage Instance of the appropriate storage controller.
 * @param Database $database Instance of the appropriate database connector.
 * @throws Exception
 */
function offloadShow(array $show, Storage $storage, Database $database) {
    $storage->offload($show["file"]);
    $database->updateLocation($show["id"], Storage::$LOCATION_MAIN);
}