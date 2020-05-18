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

function logToDatabase($userID, $actionType, $actionDetail) {
    if (!empty($userID)) {
        $connections = require "databaseConnections.php";
        $logQuery = $connections["submissions"]->prepare("INSERT INTO log (user, action_type, action_detail) VALUES (?, ?, ?)");
        $logQuery->bindParam("sss", $userID, $actionType, $actionDetail);
        $logQuery->execute();
    }
}

function notificationEmail($subject, $body) {
    require_once __DIR__ . "../vendor/autoload.php";
    $config = require 'config.php';

    // Instantiation and passing `true` enables exceptions
    $mail = new PHPMailer(true);

    try {
        //Server settings
        $mail->SMTPDebug = SMTP::DEBUG_SERVER;                      // Enable verbose debug output
        $mail->isSMTP();                                            // Send using SMTP
        $mail->Host = $config["smtpServer"];                    // Set the SMTP server to send through
        $mail->Port = $config["smtpPort"];                                    // TCP port to connect to, use 465 for `PHPMailer::ENCRYPTION_SMTPS` above
        $mail->SMTPAuth = $config["smtpAuth"];                                   // Enable SMTP authentication
        if ($config["smtpAuth"]) {
            $mail->Username = $config["smtpUsername"];                     // SMTP username
            $mail->Password = $config["smtpPassword"];                               // SMTP password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         // Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` encouraged
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