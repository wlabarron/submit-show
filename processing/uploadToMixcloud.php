<?php
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
        $showFile = new CURLFile($show["file"]);

        // basic data
        $postData = array(
           'mp3' => $showFile,
           'name' => $show["title"],
           'description' => $show["description"]
        );

        // TODO images don't send
        // if there's an image
        if (isset($show["image"])) {
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

        error_log(json_encode($tags));
        error_log(json_encode($tags[0]));
        error_log(json_encode($tags[0][0]));
        error_log(json_encode($tags[0][1]));

        // add each tag to the POST data
        for ($i = 0; $i < sizeof($tags[0]); $i++) {
            $postData["tags-" . $i . "-tag"] = $tags[0][$i];
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

        // delete the temporary image file
        unlink($config["tempDirectory"] . "/img.png");
    }
}