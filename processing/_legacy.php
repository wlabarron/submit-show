<?php


/**
 * Moves a show file from the holding directory to a more permanent storage location, and returns what that location is.
 * @throws Exception If the show file can't be found in the holding directory or can't be moved to its new home.
 */
function moveShowFileFromHolding() {
    $config = require __DIR__ . '/config.php';

    if (is_null($this->fileName)) throw new Exception("File can't be moved as no file name stored in the show data object yet.");

    // if the file exists in the holding folder of uploaded files
    if (file_exists($config["holdingDirectory"] . "/" . $this->fileName)) {
        // if S3 is configured
        if (!empty($config["s3Endpoint"])) {
            // The show is waiting to go to S3
            $this->fileLocation = showData::$LOCATION_WAITING;
            $moveTarget = $config["waitingUploadsFolder"];
        } else {
            // The show is staying locally on this server
            $this->fileLocation = showData::$LOCATION_LOCAL;
            $moveTarget = $config["uploadFolder"];
        }

        // move the folder from the holding location to its target
        if (!rename($config["holdingDirectory"] . "/" . $this->fileName, $moveTarget . "/" . $this->fileName)) {
            throw new Exception("Couldn't move " . $this->fileName . " from holding directory to " . $this->fileLocation);
        }
    } else {
        // Can't find the uploaded show file in the holding folder
        throw new Exception("Can't find uploaded show file in the holding folder. Was looking for " .
            $this->fileName . ".");
    }
}

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

function configS3() {
    // if S3 is configured
    if (!empty($config["s3Endpoint"])) {
        try {
            // create S3 client
            $s3Client = new S3Client([
                'endpoint' => $config["s3Endpoint"],
                'region' => $config["s3Region"],
                'version' => 'latest',
                'credentials' => [
                    'key' => $config["s3AccessKey"],
                    'secret' => $config["s3Secret"],
                ],
            ]);
        } catch (S3Exception $e) {
            logWithLevel("error", "Couldn't create S3 client. Error:\n" . $e->getMessage());
        }
    } else {
        $s3Client = null;
    }
}