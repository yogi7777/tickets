<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);
$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

// Login überprüfen
$auth->requireLogin();

// Filter Parameter
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$status = $_GET['status'] ?? 'active';  // Default: nur aktive
$product_id = $_GET['product_id'] ?? 'all';
$ticket_status = $_GET['ticket_status'] ?? 'pending';  // Default: nur ausstehende

// Nachricht Verarbeitung
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'cancel':
            $booking_id = $_POST['booking_id'] ?? null;
            if ($booking_id) {
                try {
                    $db->beginTransaction();

                    // Buchungsdaten abrufen für E-Mail
                    $query = "SELECT b.*, p.name as product_name 
                             FROM bookings b 
                             JOIN products p ON b.product_id = p.id 
                             WHERE b.id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$booking_id]);
                    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

                    // Buchung stornieren
                    $query = "UPDATE bookings SET booking_status = 'cancelled' WHERE id = ?";
                    $stmt = $db->prepare($query);
                    if ($stmt->execute([$booking_id])) {
                        // Stornierungsmail senden
                        $emailData = array(
                            'firstName' => $booking['customer_firstname'],
                            'lastName' => $booking['customer_lastname'],
                            'email' => $booking['customer_email'],
                            'date' => $booking['booking_date'],
                            'product_name' => $booking['product_name'],
                            'number_of_tickets' => $booking['number_of_tickets']
                        );
                        $mailer = new Mailer();
                        $mailer->sendCancellationEmail($emailData);
                        
                        $db->commit();
                        $message = 'Buchung erfolgreich storniert';
                        $messageType = 'success';
                    }
                } catch (Exception $e) {
                    $db->rollBack();
                    $message = 'Fehler bei der Stornierung';
                    $messageType = 'danger';
                }
            }
            break;

        case 'pickup_ticket':
            $booking_id = $_POST['booking_id'] ?? null;
            $serial_ids = $_POST['serials'] ?? [];  // Array der ausgewählten Seriennummern
        
            if ($booking_id && !empty($serial_ids)) {
                try {
                    $db->beginTransaction();
        
                    // Buchung aktualisieren
                    $query = "UPDATE bookings SET ticket_picked_up = NOW() WHERE id = ?";
                    $stmt = $db->prepare($query);
                    
                    if ($stmt->execute([$booking_id])) {
                        // Seriennummern zuweisen
                        $insertQuery = "INSERT INTO booking_serials (booking_id, serial_id, picked_up_at) VALUES (?, ?, NOW())";
                        $insertStmt = $db->prepare($insertQuery);
                        
                        // Status der Seriennummern aktualisieren
                        $updateQuery = "UPDATE product_serials SET status = 'picked_up' WHERE id = ?";
                        $updateStmt = $db->prepare($updateQuery);
        
                        foreach ($serial_ids as $serial_id) {
                            $insertStmt->execute([$booking_id, $serial_id]);
                            $updateStmt->execute([$serial_id]);
                        }
        
                        $db->commit();
                        $message = 'Ticket-Abholung erfolgreich vermerkt';
                        $messageType = 'success';
                        
                        // Seite neu laden
                        header('Location: ' . $_SERVER['PHP_SELF']);
                        exit;
                    }
                } catch (Exception $e) {
                    $db->rollBack();
                    $message = 'Fehler bei der Ticket-Abholung: ' . $e->getMessage();
                    $messageType = 'danger';
                }
            }
            break;

        case 'return_ticket':
            $booking_id = $_POST['booking_id'] ?? null;
            if ($booking_id) {
                try {
                    $db->beginTransaction();
        
                    // Buchung aktualisieren
                    $query = "UPDATE bookings SET ticket_returned = NOW(), booking_status = 'done' WHERE id = ?";
                    $stmt = $db->prepare($query);
                    
                    if ($stmt->execute([$booking_id])) {
                        // Seriennummern wieder auf 'available' setzen
                        $query = "UPDATE product_serials ps 
                                 JOIN booking_serials bs ON ps.id = bs.serial_id 
                                 SET ps.status = 'available',
                                     bs.returned_at = NOW()
                                 WHERE bs.booking_id = ?";
                        $stmt = $db->prepare($query);
                        $stmt->execute([$booking_id]);
        
                        $db->commit();
                        $message = 'Ticket-Rückgabe erfolgreich vermerkt';
                        $messageType = 'success';
                    }
                } catch (Exception $e) {
                    $db->rollBack();
                    $message = 'Fehler bei der Ticket-Rückgabe';
                    $messageType = 'danger';
                }
            }
            break;
    }
}

// Buchungen abrufen mit angepasstem Filter
$query = "SELECT b.*, p.name as product_name 
          FROM bookings b 
          JOIN products p ON b.product_id = p.id 
          WHERE 1=1";
$params = [];

if ($status !== 'all') {
    $query .= " AND b.booking_status = ?";
    $params[] = $status;
}

if ($product_id !== 'all') {
    $query .= " AND b.product_id = ?";
    $params[] = $product_id;
}

if ($date_from) {
    $query .= " AND b.booking_date >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $query .= " AND b.booking_date <= ?";
    $params[] = $date_to;
}

if ($ticket_status === 'pending') {
    $query .= " AND b.ticket_returned IS NULL";
} elseif ($ticket_status === 'returned') {
    $query .= " AND b.ticket_returned IS NOT NULL";
}

$query .= " ORDER BY b.booking_date DESC, b.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Produkte für Filter abrufen
$query = "SELECT id, name FROM products WHERE active = 1 ORDER BY name";
$stmt = $db->prepare($query);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buchungsverwaltung</title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="../assets/css/dark-mode.css" rel="stylesheet">
    <style>
        .serial-number {
            padding: 2px 0;
            font-family: monospace;
        }
        .serial-number i {
            margin-left: 5px;
        }
        [data-bs-theme="dark"] .serial-number {
            color: #adb5bd;
        }
    </style>
</head>
<body>
<?php include "../includes/admin_menu.php"; ?>

    <div class="container mt-4">
        <h1 class="mb-4">Buchungsverwaltung</h1>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Angepasster Filter -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-2">
                        <label for="date_from" class="form-label">Von Datum</label>
                        <input type="date" class="form-control" id="date_from" name="date_from" 
                               value="<?php echo $date_from; ?>">
                    </div>
                    
                    <div class="col-md-2">
                        <label for="date_to" class="form-label">Bis Datum</label>
                        <input type="date" class="form-control" id="date_to" name="date_to" 
                               value="<?php echo $date_to; ?>">
                    </div>
                    
                    <div class="col-md-2">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>Alle</option>
                            <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Aktiv</option>
                            <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Storniert</option>
                            <option value="done" <?php echo $status === 'done' ? 'selected' : ''; ?>>Erledigt</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label for="ticket_status" class="form-label">Ticket Status</label>
                        <select class="form-select" id="ticket_status" name="ticket_status">
                            <option value="all" <?php echo $ticket_status === 'all' ? 'selected' : ''; ?>>Alle</option>
                            <option value="pending" <?php echo $ticket_status === 'pending' ? 'selected' : ''; ?>>Ausstehend</option>
                            <option value="returned" <?php echo $ticket_status === 'returned' ? 'selected' : ''; ?>>Zurückgegeben</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="product_id" class="form-label">Produkt</label>
                        <select class="form-select" id="product_id" name="product_id">
                            <option value="all">Alle</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?php echo $product['id']; ?>" 
                                        <?php echo $product_id == $product['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($product['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">Filtern</button>
                        <a href="bookings.php" class="btn btn-secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Buchungsliste -->
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Buchungsdatum</th>
                        <th>Produkt</th>
                        <th>Kunde</th>
                        <th>E-Mail</th>
                        <th>Tickets</th>
                        <th>Status</th>
                        <th>Ticket Status</th>
                        <th>Gebucht am</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $booking): 
                        $booking_date = new DateTime($booking['booking_date']);
                        $next_day = (new DateTime($booking['booking_date']))->modify('+1 day');
                        $today = new DateTime();
                        $is_overdue = $today > $next_day && $booking['ticket_returned'] === null && $booking['booking_status'] === 'active';
                    ?>
                        <tr class="<?php echo $is_overdue ? 'table-danger' : ''; ?>">
                            <td><?php echo date('d.m.Y', strtotime($booking['booking_date'])); ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($booking['product_name']); ?></strong>
                                <?php if ($booking['ticket_picked_up']): ?>
                                    <div class="small mt-2 text-muted">
                                        <?php 
                                        // Abgeholte Seriennummern für diese Buchung abrufen
                                        $query = "SELECT ps.serial_number, bs.returned_at 
                                                 FROM booking_serials bs 
                                                 JOIN product_serials ps ON bs.serial_id = ps.id 
                                                 WHERE bs.booking_id = ? AND bs.picked_up_at IS NOT NULL";
                                        $stmt = $db->prepare($query);
                                        $stmt->execute([$booking['id']]);
                                        $serials = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                        
                                        foreach ($serials as $serial): ?>
                                            <div class="serial-number">
                                                <?php echo htmlspecialchars($serial['serial_number']); ?>
                                                <?php if ($serial['returned_at']): ?>
                                                    <i class="bi bi-check-circle-fill text-success" title="zurückgegeben"></i>
                                                <?php else: ?>
                                                    <i class="bi bi-arrow-right-circle-fill text-warning" title="ausgeliehen"></i>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($booking['customer_firstname'] . ' ' . $booking['customer_lastname']); ?></td>
                            <td><?php echo htmlspecialchars($booking['customer_email']); ?></td>
                            <td><?php echo $booking['number_of_tickets']; ?></td>
                            <td>
                                <span class="badge <?php echo $booking['booking_status'] === 'active' ? 'bg-success' : 
                                                                ($booking['booking_status'] === 'done' ? 'bg-secondary' : 
                                                                ($booking['booking_status'] === 'cancelled' ? 'bg-secondary' : 'bg-danger')); ?>">
                                    <?php echo $booking['booking_status'] === 'active' ? 'Aktiv' : 
                                                ($booking['booking_status'] === 'done' ? 'Erledigt' : 
                                                ($booking['booking_status'] === 'cancelled' ? 'Storniert' : ''));  ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($booking['ticket_picked_up'] && !$booking['ticket_returned']): ?>
                                    <span class="badge bg-warning">
                                        <i class="bi bi-arrow-right"></i> Abgeholt am <br><?php echo date('d.m.Y H:i', strtotime($booking['ticket_picked_up'])); ?>
                                    </span>
                                <?php elseif ($booking['ticket_returned']): ?>
                                    <span class="badge bg-success">
                                        <i class="bi bi-arrow-right"></i> Zurückgegeben am <br><?php echo date('d.m.Y H:i', strtotime($booking['ticket_returned'])); ?>
                                    </span>
                                    <?php elseif ($booking['booking_status'] === 'cancelled'): ?>
                                        <span class="badge bg-secondary">Storniert</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Ausstehend</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('d.m.Y H:i', strtotime($booking['created_at'])); ?></td>
                            <td>
                                <?php if ($booking['booking_status'] === 'active'): ?>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Wirklich stornieren?');">
                                        <input type="hidden" name="action" value="cancel">
                                        <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                        <input type="hidden" name="customer_email" value="<?php echo $booking['customer_email']; ?>">
                                        <input type="hidden" name="number_of_tickets" value="<?php echo $booking['number_of_tickets']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" title="Stornieren">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                    <?php if (!$booking['ticket_picked_up']): ?>
                                        <button type="button" class="btn btn-sm btn-warning" 
                                                onclick="showPickupModal(<?php echo $booking['id']; ?>, <?php echo $booking['number_of_tickets']; ?>, <?php echo $booking['product_id']; ?>)" 
                                                title="Ticket abgeholt">
                                            <i class="bi bi-arrow-right-circle"></i>
                                        </button>
                                        <?php endif; ?>

                                        <?php if ($booking['ticket_picked_up'] && !$booking['ticket_returned']): ?>
                                            <button type="button" class="btn btn-sm btn-success" 
                                                    onclick="showReturnModal(<?php echo $booking['id']; ?>)" 
                                                    title="Ticket zurück">
                                                <i class="bi bi-arrow-return-left"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <!-- Modal für Seriennummern bei Abholung -->
<div class="modal fade" id="pickupModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Seriennummern auswählen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
            <form id="pickupForm" method="POST">
                <input type="hidden" name="action" value="pickup_ticket">
                <input type="hidden" name="booking_id" id="pickupBookingId">
                <input type="hidden" name="required_tickets" id="requiredTickets">
                
                <div class="mb-3">
                    <label for="serialsSelection" class="form-label">Verfügbare Seriennummern</label>
                    <div id="serialsSelection">
                        <!-- Wird dynamisch befüllt -->
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">Abholung bestätigen</button>
            </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal für Seriennummern bei Rückgabe -->
<div class="modal fade" id="returnModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Seriennummern überprüfen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Ausgegebene Seriennummern:</label>
                    <div id="returnSerialNumbers" class="p-3 bg-light rounded text-dark"></div>
                </div>
                <form id="returnForm" method="POST">
                    <input type="hidden" name="action" value="return_ticket">
                    <input type="hidden" name="booking_id" id="returnBookingId">
                    <button type="submit" class="btn btn-primary">Rückgabe bestätigen</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function showPickupModal(bookingId, requiredTickets, productId) {
    document.getElementById('pickupBookingId').value = bookingId;
    document.getElementById('requiredTickets').value = requiredTickets;
    
    // Verfügbare Seriennummern laden
    fetch(`get_available_serials.php?product_id=${productId}`)
        .then(response => response.json())
        .then(serials => {
            const container = document.getElementById('serialsSelection');
            container.innerHTML = serials.map(serial => `
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" 
                           name="serials[]" value="${serial.id}" 
                           id="serial_${serial.id}">
                    <label class="form-check-label" for="serial_${serial.id}">
                        ${serial.serial_number}
                    </label>
                </div>
            `).join('');

            // Maximale Auswahl begrenzen
            const checkboxes = container.querySelectorAll('input[type="checkbox"]');
            let checkedCount = 0;
            checkboxes.forEach(cb => {
                cb.addEventListener('change', function() {
                    if (this.checked) checkedCount++;
                    else checkedCount--;
                    
                    if (checkedCount >= requiredTickets) {
                        checkboxes.forEach(cb => {
                            if (!cb.checked) cb.disabled = true;
                        });
                    } else {
                        checkboxes.forEach(cb => cb.disabled = false);
                    }
                });
            });
        });

    const modal = new bootstrap.Modal(document.getElementById('pickupModal'));
    modal.show();
}

function showReturnModal(bookingId) {
    document.getElementById('returnBookingId').value = bookingId;
    
    // Ausgegebene Seriennummern laden
    fetch(`get_issued_serials.php?booking_id=${bookingId}`)
        .then(response => response.json())
        .then(serials => {
            document.getElementById('returnSerialNumbers').innerHTML = serials.map(serial => `
                <div>${serial.serial_number}</div>
            `).join('');
        });

    const modal = new bootstrap.Modal(document.getElementById('returnModal'));
    modal.show();
}
</script>

    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/dark-mode.js"></script>
</body>
</html>
