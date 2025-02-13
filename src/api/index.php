<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../config/database.php';
include_once '../includes/functions.php';

// Debug-Logging aktivieren
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '../logs/api_errors.log');

error_log('=== Neue API-Anfrage ===');
error_log('Request Method: ' . $_SERVER['REQUEST_METHOD']);
error_log('Request URI: ' . $_SERVER['REQUEST_URI']);

$database = new Database();
$db = $database->getConnection();

// URL parsing für Routing
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path_parts = explode('/', trim($request_uri, '/'));
$endpoint = end($path_parts);

error_log('Endpoint: ' . $endpoint);

try {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            if (strpos($request_uri, 'product/') !== false) {
                // Produkt Details abrufen
                $product_id = intval($path_parts[count($path_parts)-1]);
                error_log('Produkt Details angefordert für ID: ' . $product_id);
                getProductDetails($db, $product_id);
            }
            elseif (strpos($request_uri, 'available-dates/') !== false) {
                // Verfügbare Daten für ein Produkt
                $product_id = intval($path_parts[count($path_parts)-1]);
                error_log('Verfügbare Daten angefordert für Produkt ID: ' . $product_id);
                getAvailableDates($db, $product_id);
            }
            elseif (strpos($request_uri, 'available-tickets/') !== false) {
                // Verfügbare Tickets für ein Datum
                $product_id = intval($path_parts[count($path_parts)-2]);
                $date = $path_parts[count($path_parts)-1];
                error_log("Verfügbare Tickets angefordert für Produkt ID: $product_id, Datum: $date");
                getAvailableTickets($db, $product_id, $date);
            }
            break;

        case 'POST':
            if ($endpoint === 'book') {
                error_log('=== Neue Buchungsanfrage ===');
                
                try {
                    // POST-Daten empfangen
                    $input = file_get_contents('php://input');
                    error_log('Empfangene Daten: ' . $input);
                    
                    $data = json_decode($input, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        // Fallback auf POST-Daten wenn kein valides JSON
                        $data = $_POST;
                        error_log('Verwende POST-Daten: ' . print_r($_POST, true));
                    }

                    // Validierung
                    if (!validateBookingData($data)) {
                        http_response_code(400);
                        echo json_encode(['error' => 'Ungültige Buchungsdaten']);
                        exit;
                    }

                    // Verfügbarkeit prüfen
                    $available = checkAvailability($db, $data['productId'], $data['date']);
                    error_log("Verfügbare Tickets: $available, Angefordert: {$data['ticketCount']}");
                    
                    if ($available < $data['ticketCount']) {
                        http_response_code(400);
                        echo json_encode(['error' => 'Nicht genügend Tickets verfügbar']);
                        exit;
                    }

                    try {
                        $db->beginTransaction();

                        // Buchung speichern
                        $query = "INSERT INTO bookings 
                                 (product_id, booking_date, customer_firstname, customer_lastname, 
                                  customer_email, number_of_tickets, booking_status) 
                                 VALUES (?, ?, ?, ?, ?, ?, 'active')";
                        
                        $stmt = $db->prepare($query);
                        $success = $stmt->execute([
                            $data['productId'],
                            $data['date'],
                            $data['firstName'],
                            $data['lastName'],
                            $data['email'],
                            $data['ticketCount']
                        ]);

                        if (!$success) {
                            throw new Exception("Datenbankfehler beim Speichern der Buchung");
                        }

                        // E-Mails versenden
                        if (!sendConfirmationEmail($data)) {
                            error_log('Fehler beim Senden der Bestätigungs-E-Mail');
                        }

                        if (!sendAdminNotification($data)) {
                            error_log('Fehler beim Senden der Admin-Benachrichtigung');
                        }

                        $db->commit();
                        echo json_encode(['success' => true]);

                    } catch (Exception $e) {
                        $db->rollBack();
                        error_log('Fehler bei der Buchungsverarbeitung: ' . $e->getMessage());
                        http_response_code(500);
                        echo json_encode(['error' => 'Buchung konnte nicht gespeichert werden']);
                    }

                } catch (Exception $e) {
                    error_log('Allgemeiner Fehler: ' . $e->getMessage());
                    http_response_code(500);
                    echo json_encode(['error' => 'Ein Fehler ist aufgetreten']);
                }
                exit;
            }
            break;
    }
} catch (Exception $e) {
    error_log('API Fehler: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}

// API Funktionen
function getProductDetails($db, $product_id) {
    error_log('Hole Produktdetails für ID: ' . $product_id);
    $query = "SELECT * FROM products WHERE id = ? AND active = 1";
    $stmt = $db->prepare($query);
    $stmt->execute([$product_id]);
    
    if ($stmt->rowCount() > 0) {
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        error_log('Produktdetails gefunden: ' . print_r($product, true));
        echo json_encode($product);
    } else {
        error_log('Kein Produkt gefunden für ID: ' . $product_id);
        http_response_code(404);
        echo json_encode(array("message" => "Produkt nicht gefunden"));
    }
}

function getAvailableDates($db, $product_id) {
    error_log('Hole verfügbare Daten für Produkt ID: ' . $product_id);
    
    // Zuerst max_tickets_per_day des Produkts abrufen
    $query = "SELECT max_tickets_per_day FROM products WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    $max_tickets = $product['max_tickets_per_day'];

    // Dann Buchungen abrufen
    $query = "SELECT booking_date, SUM(number_of_tickets) as booked_tickets 
              FROM bookings 
              WHERE product_id = ? 
              AND booking_status = 'active'
              GROUP BY booking_date";
    $stmt = $db->prepare($query);
    $stmt->execute([$product_id]);
    
    $dates = array();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['booked_tickets'] >= $max_tickets) {
            $dates[] = array(
                "start" => $row['booking_date'],
                "display" => "background",
                "color" => "#ff0000"
            );
        }
    }
    
    error_log('Gefundene Daten: ' . print_r($dates, true));
    echo json_encode($dates);
}

function getAvailableTickets($db, $product_id, $date) {
    error_log("Prüfe Verfügbarkeit für Produkt ID: $product_id, Datum: $date");
    
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
    
    error_log("Max Tickets: $max_tickets, Gebucht: $total_booked, Verfügbar: $available");
    echo json_encode(array("available" => max(0, $available)));
}
?>
