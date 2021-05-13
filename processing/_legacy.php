<?php

/**
 * Checks if the uploaded image meets the given criteria, and if so, returns the image as a blob.
 * @param $fileUpload object The image file upload object POSTed to the server (like {@code $_FILES["image"]}).
 * @throws Exception If the image fails validation.
 */
function handleImageUpload(object $fileUpload) {
    // If they actually have uploaded an image
    if (!empty($fileUpload["name"])) {
        // Get and check the image's file type
        $fileType = pathinfo($fileUpload["name"], PATHINFO_EXTENSION);
        $allowTypes = array('jpg', 'png', 'jpeg');
        if (!in_array(strtolower($fileType), $allowTypes)) { // TODO better image type checking
            throw new Exception("Uploaded image format not permitted.");
        }

        $config = require 'config.php';

        // Check the image's size
        if ($fileUpload["size"] > $config["maxShowImageSize"]) {
            throw new Exception("Uploaded image too large.");
        }

        // Return the image data as a blob
        $this->image = file_get_contents($fileUpload['tmp_name']);
    }
}

function logToDatabase($userID, $actionType, $actionDetail) {
    if (!empty($userID)) {
        $connections = require "databaseConnections.php";
        $logQuery = $connections["submissions"]->prepare("INSERT INTO log (user, action_type, action_detail) VALUES (?, ?, ?)");
        $logQuery->bind_param("sss", $userID, $actionType, $actionDetail);
        if (!$logQuery->execute()) {
            logWithLevel("error", "Failed to log user action to database. Details:\n" . $logQuery->error);
        }
    }
}

function notificationEmail($recipient, $subject, $body, $cc = null) {
    require __DIR__ . "/../vendor/autoload.php";
    $config = require 'config.php';

    // Instantiation and passing `true` enables exceptions
    $mail = new PHPMailer(true);

    try {
        //Server settings
        $mail->isSMTP();
        $mail->Host = $config["smtpServer"];
        $mail->Port = $config["smtpPort"];
        $mail->SMTPAuth = $config["smtpAuth"];
        if ($config["smtpAuth"]) {
            $mail->Username = $config["smtpUsername"];
            $mail->Password = $config["smtpPassword"];
            if ($config["smtpAuth"] == "ssl") {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else if ($config["smtpAuth"] == "tls") {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }
        }

        //Recipients
        $mail->setFrom($config["smtpSendAddress"], $config["smtpSendName"]);
        $mail->addAddress($recipient);     // Add a recipient

        if (!empty($cc)) { // if a CC receipt has been specified
            $mail->addCC($cc); // add the recipient
        }

        // Content
        $mail->isHTML(false);                                  // Set email format to plain text
        $mail->Subject = $subject;
        $mail->Body = $body;

        $mail->send();
    } catch (Exception $e) {
        logWithLevel("error", "Failed to send email with subject " . $subject . ". Mailer Error: {$mail->ErrorInfo}");
    }
}

function shortenURL($url) {
    $config = require 'config.php';

    // if YOURLS is not configured, just return the URL
    if (empty($config["yourlsSignature"])) {
        return $url;
    } else {
        // Turn the signature into a time-limited version
        $timestamp = time();
        $signature = hash('sha512', $timestamp . $config["yourlsSignature"]);

        // Set up the request
        $ch = curl_init($config["yourlsApiUrl"]);
        curl_setopt($ch, CURLOPT_HEADER, 0);            // No header in the result
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Return, do not echo result
        curl_setopt($ch, CURLOPT_POST, 1);              // This is a POST request
        curl_setopt($ch, CURLOPT_POSTFIELDS, array(           // Data to POST
            'timestamp' => $timestamp,
            'hash' => 'sha512',
            'signature' => $signature,
            'action' => 'shorturl',
            'url' => $url,
            'format' => 'json'
        ));

        // Fetch and return content
        $data = curl_exec($ch);
        curl_close($ch);

        // Return the short URL
        $data = json_decode($data);
        return $data->shorturl;
    }
}


function cron() {
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
             // process show
            if (isset($response["result"]["success"]) && $response["result"]["success"]) {
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
}