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
    // if a special show was submitted
    if ($showName == "special") {
        // get special show details in array
        // replace special characters with spaces for the file name
        $name = preg_replace("/\W/", " ", $specialShowName);
        $presenter = preg_replace("/\W/", " ", $presenterShowName);

        // put details into an array and return it
        $details = [
            "name" => $name,
            "presenter" => $presenter
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

    // if the file name doesn't have the expected type of extension
    $fileNameSplit = explode(".", $uploadedFileName);

    return $details["presenter"] . "-" . $details["name"] . " " . $date . "." . end($fileNameSplit);
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
            error_log("Failed to log user action to database. Details:\n" . $logQuery->error);
        }
    }
}

function notificationEmail($subject, $body) {
    require_once __DIR__ . "/../vendor/autoload.php";
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
        $mail->addAddress($config["smtpRecipient"]);     // Add a recipient

        // Content
        $mail->isHTML(false);                                  // Set email format to plain text
        $mail->Subject = $subject;
        $mail->Body = $body;

        $mail->send();
    } catch (Exception $e) {
        error_log("Failed to send email with subject " . $subject . ". Mailer Error: {$mail->ErrorInfo}");
    }
}