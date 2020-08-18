<?php

require 'usefulFunctions.php';

use Aws\S3\Exception\S3Exception;
use Flow\FileOpenException;
use Flow\Uploader;

logWithLevel("info", "Cron requested");

if (mkdir(__DIR__ . '/showSubmissionsCronRunning.lock', 0700)) {
    logWithLevel("info", "Cron running");
    $config = require 'config.php';
    $connections = require 'databaseConnections.php';
    require __DIR__ . "/../vendor/autoload.php";
    logWithLevel("info", "Cron: Requires complete");

    // get the shows due to publish, delete, and move to S3
    $showsDueToPublish = $connections["submissions"]->query("SELECT * FROM submissions WHERE `end-datetime` < CURRENT_TIMESTAMP AND `deletion-datetime` IS NULL");
    $showsDueForDeletion = $connections["submissions"]->query("SELECT * FROM submissions WHERE `deletion-datetime` < CURRENT_TIMESTAMP");
    $showsWaitingForS3 = $connections["submissions"]->query("SELECT * FROM submissions WHERE file_location = 'waiting'");
    logWithLevel("trace", "Cron: Queried for details");

    // prepare queries for removing published shows
    $removePublishedTags = $connections["submissions"]->prepare("DELETE FROM tags WHERE submission = ?");
    $removeShowSubmission = $connections["submissions"]->prepare("DELETE FROM submissions WHERE id = ?");

    // prepare query for marking a show for deletion
    $noteShowForDeletion = $connections["submissions"]->prepare("UPDATE submissions SET `deletion-datetime` = FROM_UNIXTIME(?) WHERE id = ?");

    // prepare query for noting in database that a show is now in S3
    $noteShowMovedToS3 = $connections["submissions"]->prepare("UPDATE submissions SET file_location = 's3' WHERE id = ?");
    logWithLevel("trace", "Cron: Prepared queries");

    // if there are any shows to publish and if we have a Mixcloud access token, for each show
    if ($showsDueToPublish->num_rows > 0 && !empty($config["mixcloudAccessToken"])) {
        logWithLevel("trace", "Cron: There are shows to publish");
        while ($show = $showsDueToPublish->fetch_assoc()) {
            logWithLevel("trace", "Cron: Processing the publication of a show");
            if ($show["file_location"] == "local") { // if file is in local storage, load it
                logWithLevel("trace", "Cron: Show is local");
                $showFile = new CURLFile($config["uploadFolder"] . "/" . $show["file"]);
                logWithLevel("trace", "Cron: CURLFile made");
            } else if ($show["file_location"] == "s3") { // if file is in S3
                logWithLevel("trace", "Cron: Show is in S3");
                try {
                    logWithLevel("trace", "Cron: Trying to transfer show");
                    // Save object to a file
                    $result = $connections["s3"]->getObject(array(
                        'Bucket' => $config["s3Bucket"],
                        'Key' => "shows/" . $show["file"],
                        'SaveAs' => $config["tempDirectory"] . "/" . $show["file"]
                    ));
                    logWithLevel("trace", "Cron: Show transferred");
                } catch (S3Exception $e) {
                    logWithLevel("error", "Couldn't get " . $show["file"] . " from S3. Error:\n" . $e->getMessage());
                }

                // open the file from S3 as a CURLFile
                $showFile = new CURLFile($config["tempDirectory"] . "/" . $show["file"]);
                logWithLevel("trace", "Cron: CURLFile made");
            } else if ($show["file_location"] == "waiting") { // if the file is waiting to go to S3
                logWithLevel("trace", "Cron: Show in waiting area");
                $showFile = new CURLFile($config["waitingUploadsFolder"] . "/" . $show["file"]);
                logWithLevel("trace", "Cron: CURLFile made");
            }

            // basic data
            $postData = array(
                'mp3' => $showFile,
                'name' => $show["title"],
                'description' => $show["description"]
            );
            logWithLevel("trace", "Cron: Prepared basic show info");

            // if there's an image
            if (!empty($show["image"])) {
                logWithLevel("trace", "Cron: There's an image");
                // turn the blob into a PNG
                $image = imagecreatefromstring($show["image"]);
                logWithLevel("trace", "Cron: PNG made");
                if ($image !== false) {
                    imagepng($image, $config["tempDirectory"] . "/img.png");
                    logWithLevel("trace", "Cron: Saved image to temp file");
                    $imagePNG = new CURLFile($config["tempDirectory"] . "/img.png");
                    logWithLevel("trace", "Cron: Made CURLFile");
                    imagedestroy($image);
                    logWithLevel("trace", "Cron: Destroyed image variable");

                    // add the image to the POST data
                    $postData['picture'] = $imagePNG;
                    logWithLevel("trace", "Cron: Added image to POST data");
                }
            }

            // get the show's tags as an array
            $showTagsQuery = $connections["submissions"]->prepare("SELECT tag FROM tags WHERE `submission` = ? ORDER BY id");
            $showTagsQuery->bind_param("i", $show["id"]);
            if (!$showTagsQuery->execute()) {
                // TODO handle this
                logWithLevel("error", "Couldn't get tags of show to publish. " . $showTagsQuery->error);
            }
            logWithLevel("trace", "Cron: Queried for show tags");
            $tags = mysqli_fetch_all(mysqli_stmt_get_result($showTagsQuery));
            logWithLevel("trace", "Cron: Got array of show tags");

            // add each tag to the POST data
            for ($i = 0; $i < sizeof($tags); $i++) {
                logWithLevel("trace", "Cron: Processing a tag");
                $postData["tags-" . $i . "-tag"] = $tags[$i][0];
                logWithLevel("trace", "Cron: Added tag to POST data");
            }

            // set up cURL
            $curl = curl_init('https://api.mixcloud.com/upload/?access_token=' . $config["mixcloudAccessToken"]);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
            logWithLevel("trace", "Cron: Set up CURL");

            // execute the cURL POST
            $response = json_decode(curl_exec($curl), true);
            logWithLevel("trace", "Cron: Executed CURL. Response: \n" . json_encode($response));

            // close the request
            curl_close($curl);
            logWithLevel("trace", "Cron: Closed CURL");

            if (isset($response["result"]["success"]) && $response["result"]["success"]) {
                logWithLevel("trace", "Cron: CURL was a success");

                // TODO error handling for queries
                // remove tags for the show - we don't need them now
                $removePublishedTags->bind_param("i", $show["id"]);
                $removePublishedTags->execute();

                // Calculate when we should delete this show
                $deletionTime = date_create();
                date_add($deletionTime, date_interval_create_from_date_string($config["retentionPeriod"]));
                $deletionTime = $deletionTime->getTimestamp();

                // note that time
                $noteShowForDeletion->bind_param("ii", $deletionTime, $show["id"]);
                $noteShowForDeletion->execute();

                logWithLevel("info", "Published submission " . $show["id"] . " to Mixcloud.");

                // if a notification email address is listed in the database, send a notification email
                if (!empty($show["notification-email"])) {
                    logWithLevel("debug", "Sending publication notification email.");
                    notificationEmail($show["notification-email"],
                        $show["title"] . " published",
                        "Hello!\n\n" .
                        "\"" . $show["title"] . "\" was just published to Mixcloud. Here's the link: " . shortenURL("https://www.mixcloud.com" . $response["result"]["key"]) . "\n\n" .
                        "Thank you!\n\n" .
                        "If you'd prefer not to receive these emails in future, leave the notification box unticked when you submit your show.");
                }
            } else {
                logWithLevel("warning", "Failed to publish submission " . $show["id"] . " to Mixcloud. Response:\n" . json_encode($response));
            }

            // delete the temporary image file, if there was an image
            if (!empty($show["image"])) {
                unlink($config["tempDirectory"] . "/img.png");
            }

            if (explode($show["file"], ":")[0] == "s3") { // if show file was from S3
                // delete the temporarily-stored file from S3
                unlink($config["tempDirectory"] . "/" . explode($show["file"], ":")[1]);
            }
        }
    }

// if there are any shows to delete, for each show
    if ($showsDueForDeletion->num_rows > 0) {
        while ($show = $showsDueForDeletion->fetch_assoc()) {
            $fileDeleted = false;

            if ($show["file_location"] == "local") { // if file is in local storage, delete it
                if (!unlink($config["uploadFolder"] . "/" . $show["file"])) {
                    logWithLevel("warning", "Couldn't delete " . $show["file"] . " from local storage.");
                } else {
                    logWithLevel("info", "Deleted " . $show["file"] . " from local storage.");
                    $fileDeleted = true;
                }
            } else if ($show["file_location"] == "s3") { // if file is in S3
                try {
                    // Delete object
                    $result = $connections["s3"]->deleteObject(array(
                        'Bucket' => $config["s3Bucket"],
                        'Key' => "shows/" . $show["file"],
                        'SaveAs' => $config["tempDirectory"] . "/" . $show["file"]
                    ));

                    logWithLevel("info", "Deleted " . $show["file"] . " from S3.");
                    $fileDeleted = true;
                } catch (S3Exception $e) {
                    logWithLevel("warning", "Couldn't delete " . $show["file"] . " from S3. Error:\n" . $e->getMessage());
                }

            } else {
                logWithLevel("error", "Invalid storage location for " . $show["file"] . ".");
            }

            if ($fileDeleted) {
                // remove show from database
                // TODO error handling for queries
                $removeShowSubmission->bind_param("i", $show["id"]);
                $removeShowSubmission->execute();
            }
        }
    }

// if there are any shows waiting to go to S3, for each show
    if ($showsWaitingForS3->num_rows > 0) {
        while ($show = $showsWaitingForS3->fetch_assoc()) {
            // if an S3 endpoint is set
            if (!empty($config["s3Endpoint"])) {
                try {
                    // send the file to S3
                    $result = $connections["s3"]->putObject([
                        'Bucket' => $config["s3Bucket"],
                        'Key' => "shows/" . $show["file"],
                        'SourceFile' => $config["waitingUploadsFolder"] . "/" . $show["file"]
                    ]);

                    // update the storage location marked in the database
                    $noteShowMovedToS3->bind_param("i", $show["id"]);
                    $noteShowMovedToS3->execute();

                    // remove the uploaded show file from local storage
                    unlink($config["waitingUploadsFolder"] . "/" . $show["file"]);

                    logWithLevel("info", "Sent " . $show["file"] . " to S3 and removed from local storage.");
                } catch (S3Exception $e) {
                    logWithLevel("error", "Couldn't move " . $show["file"] . " to S3. Error:\n" . $e->getMessage());
                }
            }
        }
    }

    // Get rid of leftover chunks and files from file uploads which never finished and forms which weren't submitted
    try {
        Uploader::pruneChunks($config["tempDirectory"]);
        Uploader::pruneChunks($config["holdingDirectory"]);
    } catch (FileOpenException $e) {
        logWithLevel("error", "Failed to prune upload remnants. Details:\n" . $e->getMessage());
    }

    rmdir(__DIR__ . '/showSubmissionsCronRunning.lock');
} else {
    logWithLevel("info", "Cron is already running at the moment. It can't be re-run until it's finished.");
}