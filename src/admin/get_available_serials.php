<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

// Login überprüfen
$auth->requireLogin();

header('Content-Type: application/json');

$product_id = $_GET['product_id'] ?? null;

if (!$product_id) {
    echo json_encode(['error' => 'Produkt ID fehlt']);
    exit;
}

try {
    // Verfügbare Seriennummern für das Produkt abrufen
    $query = "SELECT id, serial_number 
              FROM product_serials 
              WHERE product_id = ? 
              AND is_active = 1
              AND status = 'available'
              ORDER BY serial_number";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$product_id]);
    $serials = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($serials);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Datenbankfehler']);
}