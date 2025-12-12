<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!function_exists('sendEmail')) {
    function sendEmail($recipientEmail, $subject, $body)
    {
        // Send registration confirmation email using PHPMailer
        $mail = new PHPMailer(true); // Pass `true` to enable exceptions

        try {
            // SMTP configuration
            // $mail->isSMTP();
            // $mail->Host = 'demo.web-informatica.info';
            // $mail->SMTPAuth = true;
            // $mail->Username = 'tripiazone@demo.web-informatica.info';
            // $mail->Password = 'Jurlx=[gD7h%';
            // $mail->SMTPSecure = 'ssl';
            // $mail->Port = 465;

            $mail->isSMTP();
            $mail->Host = 'raulo.dev';
            $mail->SMTPAuth = true;
            $mail->Username = 'tripiazone@raulo.dev';
            $mail->Password = 'w94fuT#PHl48geAmoo';
            $mail->SMTPSecure = 'ssl';
            $mail->Port = 465;

            //$mail->isSMTP();
            //$mail->Host = 'smtp.gmail.com';
            //$mail->SMTPAuth = true;
            //$mail->Username = 'paulcapauno@gmail.com';
            //$mail->Password = 'poxlgihgqtcidcdd';
            //$mail->SMTPSecure = 'ssl';
            //$mail->Port = 465;

            // Set sender and recipient
            $mail->setFrom('tripiazone@raulo.dev', 'Tripiazone');
            $mail->addAddress($recipientEmail);

            // Set email content
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8'; // Establece la codificación a UTF-8
            $mail->Subject = $subject;
            $mail->Body = $body;

            // Send email
            $mail->send();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
