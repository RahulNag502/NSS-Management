<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Correct path fix
require_once __DIR__ . '/../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';

class EmailSender {

    private $mail;

    public function __construct() {
        $this->mail = new PHPMailer(true);

        // SMTP Settings (Gmail)
        $this->mail->isSMTP();
        $this->mail->Host       = 'smtp.gmail.com';
        $this->mail->SMTPAuth   = true;
        $this->mail->Username   = 'thefpvt@gmail.com';   // your email
        $this->mail->Password   = 'gyfh enrl igal hzmq'; // your app password
        $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mail->Port       = 587;

        $this->mail->setFrom('thefpvt@gmail.com', 'NSS Navneet');
    }

    public function sendEmail($to, $subject, $message, $isHTML = true) {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($to);
            $this->mail->isHTML($isHTML);
            $this->mail->Subject = $subject;
            $this->mail->Body    = $message;

            $this->mail->send();
            return ['success' => true, 'message' => 'Email sent successfully'];

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Mailer Error: ' . $this->mail->ErrorInfo];
        }
    }

    public function sendWelcomeEmail($name, $email, $id, $password = null) {
        $subject = "Welcome to NSS";
        $message = "<h2>Welcome $name!</h2><p>Your Volunteer ID: <b>$id</b></p>";
        return $this->sendEmail($email, $subject, $message);
    }

    public function sendEventRegistrationEmail($name, $email, $event, $date, $location) {
        $subject = "Event Registered";
        $message = "<h3>Hello $name</h3>
                    <p>You registered for <b>$event</b></p>
                    <p>Date: $date<br>Location: $location</p>";
        return $this->sendEmail($email, $subject, $message);
    }

    public function sendCertificateEmail($name, $email, $type, $code) {
        $subject = "Your NSS Certificate";
        $message = "<h3>Congrats $name!</h3><p>Certificate Code: <b>$code</b></p>";
        return $this->sendEmail($email, $subject, $message);
    }

   public function sendPasswordResetEmail($name, $email, $token) {

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];

    // ✅ Your corrected folder name
    $projectFolder = "2";

    $link = "$protocol://$host/$projectFolder/reset_password.php?token=$token";

    $subject = "Reset Password";
    $message = "<p>Hello $name, reset your password here:</p><a href='$link'>$link</a>";

    return $this->sendEmail($email, $subject, $message);
}
  /* ✅ NEW FUNCTION FOR EVENT NOTIFICATION */
    public function sendNewEventNotificationEmail($name, $email, $eventTitle, $eventDate, $location) {

        $subject = "New NSS Event - Register Now";

        $message = "
            <h3>Hello $name,</h3>
            <p>A new NSS event has been added.</p>
            <p><b>Event:</b> $eventTitle</p>
            <p><b>Date:</b> $eventDate</p>
            <p><b>Location:</b> $location</p>
            <p>Please login to NSS portal and register.</p>
        ";

        return $this->sendEmail($email, $subject, $message);
    }
}
?>
