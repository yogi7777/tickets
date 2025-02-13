<?php
require_once __DIR__ . '/Mailer.php';

// Booking validation function
function validateBookingData($data) {
    error_log('Validiere Buchungsdaten: ' . print_r($data, true));
    
    $required_fields = ['productId', 'date', 'firstName', 'lastName', 'email', 'ticketCount'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            error_log("Validierung fehlgeschlagen: Feld '$field' fehlt oder ist leer");
            return false;
        }
    }
    
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        error_log('Validierung fehlgeschlagen: Ungültige E-Mail-Adresse');
        return false;
    }
    
    if (!is_numeric($data['ticketCount']) || $data['ticketCount'] < 1 || $data['ticketCount'] > 4) {
        error_log('Validierung fehlgeschlagen: Ungültige Ticketanzahl');
        return false;
    }
    
    return true;
}

// Mail functions
function sendConfirmationEmail($bookingData) {
    $mailer = new Mailer();
    return $mailer->sendConfirmationEmail($bookingData);
}

function sendAdminNotification($bookingData) {
    $mailer = new Mailer();
    return $mailer->sendAdminNotification($bookingData);
}

// Availability check
function checkAvailability($db, $product_id, $date) {
    // Zuerst max_tickets_per_day des Produkts abrufen
    $query = "SELECT max_tickets_per_day FROM products WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    $max_tickets = $product['max_tickets_per_day'];

    // Dann bereits gebuchte Tickets zählen
    $query = "SELECT SUM(number_of_tickets) as total_booked_tickets 
              FROM bookings 
              WHERE product_id = ? 
              AND booking_date = ? 
              AND booking_status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->execute([$product_id, $date]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $total_booked = intval($result['total_booked_tickets']) ?: 0;
    $available = $max_tickets - $total_booked;
    
    error_log("Produkt ID: $product_id, Max Tickets: $max_tickets, Gebucht: $total_booked, Verfügbar: $available");
    return max(0, $available);
}
