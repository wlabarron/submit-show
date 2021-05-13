<?php


use PHPMailer\PHPMailer\PHPMailer;

class Email {
    static function send($recipient, $subject, $body, $cc = null) {
        require __DIR__ . "/../vendor/autoload.php";
        $config = require 'config.php';

        // Instantiation and passing `true` enables exceptions
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host     = $config["smtpServer"];
            $mail->Port     = $config["smtpPort"];
            $mail->SMTPAuth = $config["smtpAuth"];
            if ($config["smtpAuth"]) {
                $mail->Username   = $config["smtpUsername"];
                $mail->Password   = $config["smtpPassword"];
                $mail->SMTPSecure = $config["smtpAuth"];
            }

            $mail->setFrom($config["smtpSendAddress"], $config["smtpSendName"]);

            $mail->addAddress($recipient);

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