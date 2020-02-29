<?php
require_once __DIR__ . '/vendor/autoload.php';

$mail = new \PHPMailer\PHPMailer\PHPMailer(true);

$mail->isSMTP();
$mail->Host = 'localhost';
$mail->Port = 8725;

$mail->addCustomHeader('X-custom-header', 'custom-value');
$mail->addCustomHeader('X-custom-header-2', 'booo');
$mail->addCustomHeader('X-custom-header-3', 'mopooo');
$mail->SMTPDebug = \PHPMailer\PHPMailer\SMTP::DEBUG_SERVER;
$mail->Timeout = 30;
$mail->setFrom('from@example.com', 'Mailer');
$mail->addAddress('joe@example.net', 'Joe User');     // Add a recipient
$mail->addAddress('ellen@example.com');               // Name is optional
$mail->addReplyTo('info@example.com', 'Information');
$mail->addCC('cc@example.com');
$mail->addBCC('bcc@example.com');

$mail->isHTML(true);
$mail->Subject = 'Here is the subject';
$mail->Body    = 'This is the HTML message body <b>in bold!</b>';
$mail->AltBody = 'This is the body in plain text for non-HTML mail clients';

if(!$mail->send()) {
    echo 'Message could not be sent.';
    echo 'Mailer Error: ' . $mail->ErrorInfo;
} else {
    echo 'Message has been sent';
}