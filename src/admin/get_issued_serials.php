<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

// Login überprüfen
$auth->requireLogin();

header('Content-Type: application/json');

$booking_id = $_GET['booking_id'] ?? null;

if (!$booking_id) {
    echo json_encode(['error' => 'Buchungs ID fehlt']);
    exit;
}

try {
    // Ausgegebene Seriennummern für die Buchung abrufen
    $query = "SELECT ps.id, ps.serial_number 
              FROM product_serials ps
              JOIN booking_serials bs ON ps.id = bs.serial_id
              WHERE bs.booking_id = ? 
              AND bs.returned_at IS NULL
              ORDER BY ps.serial_number";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$booking_id]);
    $serials = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($serials);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Datenbankfehler']);
}