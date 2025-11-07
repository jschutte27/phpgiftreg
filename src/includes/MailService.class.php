<?php
/**
 * Mail Service class for handling email sending via PHPMailer
 * Supports both internal PHP mail() and external SMTP services
 */

// Load Composer autoloader to get PHPMailer
require_once(dirname(__FILE__) . '/../vendor/autoload.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class MailService {
    private $mailer;
    private $options;
    private $useSmtp;
    
    public function __construct($options) {
        $this->options = $options;
        $this->useSmtp = isset($options['SMTP_ENABLED']) && $options['SMTP_ENABLED'];
        
        $this->log("=== MailService initialized ===");
        $this->log("SMTP_ENABLED: " . ($this->useSmtp ? "Yes" : "No"));
        if ($this->useSmtp) {
            $this->log("SMTP_HOST: " . ($this->options['SMTP_HOST'] ?? 'not set'));
            $this->log("SMTP_PORT: " . ($this->options['SMTP_PORT'] ?? 'not set'));
        }
        
        if ($this->useSmtp) {
            $this->initializeSmtp();
        }
    }
    
    /**
     * Log message to console (error_log)
     */
    private function log($message) {
        error_log("[MailService] $message");
    }
    
    /**
     * Initialize PHPMailer for SMTP
     */
    private function initializeSmtp() {
        try {
            $this->mailer = new PHPMailer(true);
            $this->log("PHPMailer instance created");
            
            // Server settings
            $this->mailer->isSMTP();
            $this->mailer->Host = $this->options['SMTP_HOST'] ?? 'localhost';
            $this->mailer->Port = (int)($this->options['SMTP_PORT'] ?? 587);
            $this->mailer->SMTPAuth = isset($this->options['SMTP_AUTH']) && $this->options['SMTP_AUTH'];
            $this->mailer->SMTPSecure = $this->options['SMTP_SECURE'] ?? PHPMailer::ENCRYPTION_STARTTLS;
            
            $this->log("SMTP connection settings: Host={$this->mailer->Host}, Port={$this->mailer->Port}, Auth={$this->mailer->SMTPAuth}, Secure={$this->mailer->SMTPSecure}");
            
            if ($this->mailer->SMTPAuth) {
                $this->mailer->Username = $this->options['SMTP_USERNAME'] ?? '';
                $this->mailer->Password = $this->options['SMTP_PASSWORD'] ?? '';
                $this->log("SMTP auth credentials set for username: {$this->mailer->Username}");
            }

            // Set default sender
            $this->mailer->setFrom($this->options['email_from'], 'Gift Registry');
            $this->mailer->addReplyTo($this->options['email_reply_to']);
            $this->log("PHPMailer initialized successfully");
        } catch (Exception $e) {
            $this->log("ERROR: PHPMailer initialization failed: " . $e->getMessage());
        }
    }
    
    /**
     * Send email via SMTP or PHP mail() function
     * 
     * @param string $to Recipient email address
     * @param string $subject Email subject
     * @param string $message Email body (plain text)
     * @return bool True if email was sent/accepted, false otherwise
     */
    public function send($to, $subject, $message) {
        $this->log("Sending email to: $to, Subject: $subject");
        
        if ($this->useSmtp) {
            return $this->sendViaSmtp($to, $subject, $message);
        } else {
            return $this->sendViaPhpMail($to, $subject, $message);
        }
    }
    
    /**
     * Send email via PHPMailer SMTP
     */
    private function sendViaSmtp($to, $subject, $message) {
        try {
            $this->log("Using SMTP method");
            $this->mailer->clearAllRecipients();
            $this->mailer->addAddress($to);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $message;
            $this->mailer->isHTML(false);
            
            $this->log("Attempting to send via SMTP...");
            
            $result = $this->mailer->send();
            
            if ($result) {
                $this->log("SUCCESS: Email sent to $to");
            } else {
                $this->log("FAILED: Email send returned false for $to");
                $this->log("Error: " . $this->mailer->ErrorInfo);
            }
            return $result;
        } catch (Exception $e) {
            $this->log("ERROR: PHPMailer exception: " . $e->getMessage());
            $this->log("Exception details: " . $this->mailer->ErrorInfo);
            return false;
        }
    }
    
    /**
     * Send email via PHP mail() function (fallback)
     */
    private function sendViaPhpMail($to, $subject, $message) {
        $this->log("Using PHP mail() method");
        
        $headers = "From: {$this->options['email_from']}\r\n";
        $headers .= "Reply-To: {$this->options['email_reply_to']}\r\n";
        $headers .= "X-Mailer: {$this->options['email_xmailer']}\r\n";
        
        $this->log("Attempting to send via PHP mail()...");
        $result = mail($to, $subject, $message, $headers);
        
        if ($result) {
            $this->log("SUCCESS: PHP mail() accepted email for $to");
        } else {
            $this->log("FAILED: PHP mail() returned false for $to");
        }
        return $result;
    }
}
?>
