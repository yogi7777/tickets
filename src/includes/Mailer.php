<?php
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';
require_once __DIR__ . '/PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer {
    private $mailer;
    private $config;

    public function __construct() {
        // Konfiguration laden und prüfen
        $configFile = __DIR__ . '/../config/smtp.php';
        if (!file_exists($configFile)) {
            error_log('SMTP Konfigurationsdatei nicht gefunden: ' . $configFile);
            throw new Exception('SMTP Konfiguration fehlt');
        }

        $this->config = require $configFile;
        
        // Konfiguration validieren
        $required = ['host', 'port', 'from_email', 'from_name', 'admin_email'];
        foreach ($required as $field) {
            if (!isset($this->config[$field]) || empty($this->config[$field])) {
                error_log("Fehlende SMTP Konfiguration: $field");
                throw new Exception('Unvollständige SMTP Konfiguration');
            }
        }

        $this->mailer = new PHPMailer(true);

        // Server settings
        $this->mailer->isSMTP();
        $this->mailer->Host = $this->config['host'];
        $this->mailer->Port = $this->config['port'];
        
        // Auth nur wenn Benutzername gesetzt
        if (!empty($this->config['username'])) {
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $this->config['username'];
            $this->mailer->Password = $this->config['password'];
        } else {
            $this->mailer->SMTPAuth = false;
        }

        // Encryption
        if (!empty($this->config['encryption'])) {
            $this->mailer->SMTPSecure = $this->config['encryption'];
        }
        
        // Default settings
        $this->mailer->setFrom($this->config['from_email'], $this->config['from_name']);
        $this->mailer->CharSet = 'UTF-8';
    }

    public function sendConfirmationEmail($bookingData) {
        try {
            // Produktname aus der Datenbank holen
            $db = new Database();
            $conn = $db->getConnection();
            $query = "SELECT name FROM products WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->execute([$bookingData['productId']]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($bookingData['email']);
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Buchungsbestätigung';
            
            $message = "
            <html>
            <head>
                <title>Ihre Buchungsbestätigung</title>
            </head>
            <body>
                <h2>Vielen Dank für Ihre Buchung!</h2>
                <p>Sehr geehrte(r) {$bookingData['firstName']} {$bookingData['lastName']},</p>
                <p>Ihre Buchung wurde erfolgreich registriert:</p>
                <ul>
                    <li>Produkt: {$product['name']}</li>
                    <li>Datum: " . date('d.m.Y', strtotime($bookingData['date'])) . "</li>
                    <li>Anzahl Tickets: {$bookingData['ticketCount']}</li>
                </ul>
                <p>Bitte holen Sie Ihre Tickets am Empfang ab.</p>
                <p>Mit freundlichen Grüßen<br>Ihr Team</p>
            </body>
            </html>";
    
            $this->mailer->Body = $message;
            return $this->mailer->send();
        } catch (Exception $e) {
            error_log("Mail Error (Confirmation): " . $e->getMessage());
            return false;
        }
    }
    
    // Das Gleiche auch für die Admin-Benachrichtigung
    public function sendAdminNotification($bookingData) {
        try {
            // Produktname aus der Datenbank holen
            $db = new Database();
            $conn = $db->getConnection();
            $query = "SELECT name FROM products WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->execute([$bookingData['productId']]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($this->config['admin_email']);
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Neue Ticketbuchung';
            
            $message = "
            <html>
            <head>
                <title>Neue Ticketbuchung</title>
            </head>
            <body>
                <h2>Neue Ticketbuchung eingegangen</h2>
                <p>Details der Buchung:</p>
                <ul>
                    <li>Name: {$bookingData['firstName']} {$bookingData['lastName']}</li>
                    <li>E-Mail: {$bookingData['email']}</li>
                    <li>Produkt: {$product['name']}</li>
                    <li>Datum: " . date('d.m.Y', strtotime($bookingData['date'])) . "</li>
                    <li>Anzahl Tickets: {$bookingData['ticketCount']}</li>
                </ul>
            </body>
            </html>";
    
            $this->mailer->Body = $message;
            return $this->mailer->send();
        } catch (Exception $e) {
            error_log("Mail Error (Admin): " . $e->getMessage());
            return false;
        }
    }

    public function sendCancellationEmail($bookingData) {
        try {
            // Produktname aus der Datenbank holen
            $db = new Database();
            $conn = $db->getConnection();
            $query = "SELECT name FROM products WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->execute([$bookingData['productId']]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($bookingData['email']);
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Buchung storniert';
            
            $message = "
            <html>
            <head>
                <title>Buchung storniert</title>
            </head>
            <body>
                <h2>Ihre Buchung wurde storniert</h2>
                <p>Sehr geehrte(r) {$bookingData['firstName']} {$bookingData['lastName']},</p>
                <p>Ihre Buchung wurde storniert.</p>
                <ul>
                    <li>Produkt: {$product['name']}</li>
                    <li>Datum: " . date('d.m.Y', strtotime($bookingData['date'])) . "</li>
                    <li>Anzahl Tickets: {$bookingData['ticketCount']}</li>
                </ul>
                <p>Bei Fragen können Sie sich gerne an uns wenden.</p>
                <p>Mit freundlichen Grüßen<br>Ihr Team</p>
            </body>
            </html>";
    
            $this->mailer->Body = $message;
            return $this->mailer->send();
        } catch (Exception $e) {
            error_log("Mail Error (Confirmation): " . $e->getMessage());
            return false;
        }
    }
}
