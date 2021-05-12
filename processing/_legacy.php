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