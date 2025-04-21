<?php
namespace App\Controllers;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require_once __DIR__ . '/../../vendor/autoload.php';

class EmailController {
    private $mailer;
    private $senderEmail;
    private $senderPassword;

    public function __construct() {
        $this->senderEmail = 'jeorgeandreielevencionado@gmail.com'; // Replace with your Gmail
        $this->senderPassword = 'alsk dutt bglo gamj'; // Replace with your Gmail App Password
        
        $this->mailer = new PHPMailer(true);
        $this->setupMailer();
    }

    private function setupMailer() {
        try {
            $this->mailer->isSMTP();
            $this->mailer->Host = 'smtp.gmail.com';
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $this->senderEmail;
            $this->mailer->Password = $this->senderPassword;
            $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mailer->Port = 587;
            $this->mailer->setFrom($this->senderEmail, 'IAS Authentication');
            $this->mailer->isHTML(true);
            
            // Debug mode for troubleshooting
            $this->mailer->SMTPDebug = SMTP::DEBUG_SERVER; // Use DEBUG_SERVER for full debugging
            $this->mailer->Debugoutput = function($str, $level) {
                file_put_contents(__DIR__ . '/../../smtp-debug.log', $str . "\n", FILE_APPEND);
            };
            
            error_log("PHPMailer setup completed successfully");
        } catch (Exception $e) {
            error_log('Mailer configuration failed: ' . $e->getMessage());
            throw new Exception('Mailer configuration failed: ' . $e->getMessage());
        }
    }

    public function sendOTP($recipientEmail, $otp) {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($recipientEmail);
            $this->mailer->Subject = 'Your OTP for Authentication';
            
            $emailBody = "
                <html>
                <body style='font-family: Arial, sans-serif;'>
                    <h2>Your One-Time Password (OTP)</h2>
                    <p>Hello,</p>
                    <p>Your OTP for authentication is: <strong style='font-size: 24px;'>{$otp}</strong></p>
                    <p>This OTP will expire in 1 minute.</p>
                    <p>If you didn't request this OTP, please ignore this email.</p>
                    <br>
                    <p>Best regards,<br>IAS Authentication Team</p>
                </body>
                </html>
            ";
            
            $this->mailer->Body = $emailBody;
            $this->mailer->AltBody = "Your OTP is: {$otp}. This OTP will expire in 1 minute.";
            
            error_log("Attempting to send email to {$recipientEmail}");
            $result = $this->mailer->send();
            error_log("Email send result: " . ($result ? "Success" : "Failed: " . $this->mailer->ErrorInfo));
            
            return $result;
        } catch (Exception $e) {
            error_log('Email sending error: ' . $e->getMessage());
            throw new Exception('Failed to send OTP: ' . $e->getMessage());
        }
    }
} 