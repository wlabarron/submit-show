<?php

use Flow\FileOpenException;
use Flow\Uploader;
use submitShow\Database;

if (mkdir(__DIR__ . '/showSubmissionsCronRunning.lock', 0700)) {
    require __DIR__ . "/../vendor/autoload.php";
    $config   = require 'config.php';
    try {
        $storage = Storage::getProvider();
    } catch (Exception $e) {
        error_log("Failed to get a storage provider instance.");
        exit();
    }
    $database = new Database();

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

    $showsDueToDelete = $database->getExpiredSubmissions();
    if ($showsDueToDelete) {
        foreach ($showsDueToDelete as $show) {
            try {
                deleteShow($show, $storage, $database);
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

    rmdir(__DIR__ . '/showSubmissionsCronRunning.lock');
} else {
    error_log("Cron is already running at the moment. It can't be re-run until it's finished.");
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
    if ($show["file_location"] == Storage::$LOCATION_WAITING) {
        $path = $config["waitingUploadsFolder"] . "/" . $show["file"];
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
                "\"" . $show["title"] . "\" was just published to Mixcloud. Here's the link: " . shortenURL("https://www.mixcloud.com" . $response["result"]["key"]) . "\n\n" .
                "Thank you!\n\n" .
                "If you'd prefer not to receive these emails in future, leave the notification box unticked when you submit your show.");
        }
    } else {
        error_log("Failed to publish submission " . $show["id"] . " to Mixcloud. Response:\n" . json_encode($response));
    }

    // delete the temporary image file, if there was an image
    if (!empty($show["image"])) unlink($config["tempDirectory"] . "/img.png");
    // delete the copy of the file used for publication
    unlink($path);
}

/**
 * Deletes the specified submission's file in the storage provider and entry in the database.
 * @param array $show Database record for the recording to publish.
 * @param Storage $storage Instance of the appropriate storage controller.
 * @param Database $database Instance of the appropriate database connector.
 * @throws Exception
 */
function deleteShow(array $show, Storage $storage, Database $database) {
    $storage->delete($show["file"]);
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