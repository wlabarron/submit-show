<?php

use Aws\S3\Exception\S3Exception;

$config = require 'config.php';
$connections = require 'databaseConnections.php';

// get the shows due to publish
$showsDueToPublish = $connections["submissions"]->query("SELECT * FROM submissions WHERE `end-datetime` < CURRENT_TIMESTAMP");

// prepare queries for removing published shows
$removePublishedTags = $connections["submissions"]->prepare("DELETE FROM tags WHERE submission = ?");
$removePublishedShows = $connections["submissions"]->prepare("DELETE FROM submissions WHERE id = ?");

// if there are any, for each show
if ($showsDueToPublish->num_rows > 0) {
    while ($show = $showsDueToPublish->fetch_assoc()) {
        $storageLocation = explode(":", $show["file"])[0];
        $storageName = explode(":", $show["file"])[1];

        if ($storageLocation == "local") { // if file is in local storage, load it
            $showFile = new CURLFile($config["uploadFolder"] . "/" . $storageName);
        } else if ($storageLocation == "s3") { // if file is in S3
            try {
                // Save object to a file
                $result = $connections["s3"]->getObject(array(
                    'Bucket' => $config["s3Bucket"],
                    'Key' => "shows/" . $storageName,
                    'SaveAs' => $config["tempDirectory"] . "/" . $storageName
                ));
            } catch (S3Exception $e) {
                error_log("Couldn't get " . $storageName . " from S3. Error:\n" . $e->getMessage());
            }

            // open the file from S3 as a CURLFile
            $showFile = new CURLFile($config["tempDirectory"] . "/" . $storageName);
        }

        // basic data
        $postData = array(
            'mp3' => $showFile,
            'name' => $show["title"],
            'description' => $show["description"]
        );

        // TODO images don't send
        // if there's an image
        if (!empty($show["image"])) {
            // turn the blob into a PNG
            $image = imagecreatefromstring($show["image"]);
            if ($image !== false) {
                imagepng($image, $config["tempDirectory"] . "/img.png");
                $imagePNG = new CURLFile($config["tempDirectory"] . "/img.png");
                imagedestroy($image);

                // add the image to the POST data
                $postData['picture'] = $imagePNG;
            }
        }

        // get the show's tags as an array
        $showTagsQuery = $connections["submissions"]->prepare("SELECT tag FROM tags WHERE `submission` = ?");
        $showTagsQuery->bind_param("i", $show["id"]);
        if (!$showTagsQuery->execute()) {
            // TODO handle this
            error_log($showTagsQuery->error);
        }
        $tags = mysqli_fetch_all(mysqli_stmt_get_result($showTagsQuery));

        // add each tag to the POST data
        for ($i = 0; $i < sizeof($tags); $i++) {
            $postData["tags-" . $i . "-tag"] = $tags[$i][0];
        }

        // set up cURL
        $curl = curl_init('https://api.mixcloud.com/upload/?access_token=' . $config["mixcloudAccessToken"]);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);

        // execute the cURL POST
        $response = json_decode(curl_exec($curl), true);

        // close the request
        curl_close($curl);

        if (isset($response["result"]["success"]) && $response["result"]["success"]) {
            $removePublishedTags->bind_param("i", $show["id"]);
            $removePublishedTags->execute();
            $removePublishedShows->bind_param("i", $show["id"]);
            $removePublishedShows->execute();
            error_log("Published submission " . $show["id"] . " to Mixcloud.");
        } else {
            error_log("Failed to publish submission " . $show["id"] . " to Mixcloud. Response:\n" . json_encode($response));
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