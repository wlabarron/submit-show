<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Email {
    static function send($recipient, $subject, $body, $cc = null) {
        require __DIR__ . "/../vendor/autoload.php";
        $config = require 'config.php';

        if (!$config["smtp"]["enabled"]) return;

        // Instantiation and passing `true` enables exceptions
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host     = $config["smtp"]["server"];
            $mail->Port     = $config["smtp"]["port"];
            $mail->SMTPAuth = $config["smtp"]["auth"];
            if ($config["smtp"]["auth"]) {
                $mail->Username   = $config["smtp"]["username"];
                $mail->Password   = $config["smtp"]["password"];
                $mail->SMTPSecure = $config["smtp"]["encryption"];
            }

            $mail->setFrom($config["smtp"]["sendAddress"], $config["smtp"]["sendName"]);

            if (is_array($recipient)){
                foreach ($recipient as &$address) {
                    $mail->addAddress($address);
                }
            } else {
                $mail->addAddress($recipient);
            }

            if (!empty($cc)) {
                $mail->addCC($cc);
            }

            $mail->isHTML(false); // format is plain text

            $mail->Subject = $subject;
            $mail->Body = $body;

            $mail->send();
        } catch (Exception $e) {
            error_log("Failed to send email: {$mail->ErrorInfo}");
        }
    }
}