<?php
// The function to make sure that user input is safe
use PHPMailer\PHPMailer\PHPMailer;

function clearUpInput($data) {
    // Remove unnecessary characters
    $data = trim($data);
    // Replace forward slashes with code
    $data = stripslashes($data);
    // Replace angle brackets and HTML characters with code
    $data = htmlspecialchars($data, ENT_QUOTES);
    // Add slashes to make sure it reaches the database properly
    $data = addslashes($data);
    // Send the data back to the program
    return $data;
}

function prepareFileName($showName, $uploadedFileName, $date, $specialShowName = null, $presenterShowName = null) {
    // TODO this is duplicated in ShowData class
    // if a special show was submitted
    if ($showName == "special") {
        // get special show details in array
        $details = [
            "name" => $specialShowName,
            "presenter" => $presenterShowName
        ];
    } else {
        $config = require "config.php";
        $connections = require "databaseConnections.php";
        // prepare a database query for show info
        $showDetailsQuery = $connections["details"]->prepare($config["oneShowQuery"]);
        $showDetailsQuery->bind_param("i", $showName);

        // get the info about the show
        $showDetailsQuery->execute();
        $details = mysqli_fetch_assoc($showDetailsQuery->get_result());
    }

    // put the date into the correct format
    $date = date("ymd", strtotime($date));

    // split the file name by '.'
    $fileNameSplit = explode(".", $uploadedFileName);

    // Decode the encoded special characters
    $details["name"] = htmlspecialchars_decode($details["name"], ENT_QUOTES);
    $details["presenter"] = htmlspecialchars_decode($details["presenter"], ENT_QUOTES);

    // replace special characters in show details with spaces for the file name
    $details["name"] = preg_replace("/\W/", " ", $details["name"]);
    $details["presenter"] = preg_replace("/\W/", " ", $details["presenter"]);

    // replace multiple spaces with a single space
    $details["name"] = preg_replace("/\s+/", " ", $details["name"]);
    $details["presenter"] = preg_replace("/\s+/", " ", $details["presenter"]);

    return trim($details["presenter"]) . "-" . trim($details["name"]) . " " . $date . "." . end($fileNameSplit);
}

function getShowDetails($showName, $specialShowName = null, $presenterShowName = null) {
    // if a special show was submitted
    if ($showName == "special") {
        return [
            "name" => $specialShowName,
            "presenter" => $presenterShowName
        ];
    } else {
        $config = require "config.php";
        $connections = require "databaseConnections.php";
        // prepare a database query for show info
        $showDetailsQuery = $connections["details"]->prepare($config["oneShowQuery"]);
        $showDetailsQuery->bind_param("i", $showName);

        // get the info about the show
        $showDetailsQuery->execute();
        return mysqli_fetch_assoc($showDetailsQuery->get_result());
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

function logWithLevel($level, $message) {
    $config = require 'config.php';

    if ($config["loggingLevel"][$level]) {
        error_log($message);
    }
}