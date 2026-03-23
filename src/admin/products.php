<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

// Login überprüfen
$auth->requireLogin();

// Aktion verarbeiten
$message = '';
$messageType = '';

function normalizeSerials(array $serials): array {
    $normalized = [];

    foreach ($serials as $serial) {
        $serial = trim($serial);

        if ($serial === '' || in_array($serial, $normalized, true)) {
            continue;
        }

        $normalized[] = $serial;
    }

    return $normalized;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $name = $_POST['name'] ?? '';
                $description = $_POST['description'] ?? '';
                $maxTickets = (int)($_POST['maxTickets'] ?? 4);
                $active = isset($_POST['active']) ? 1 : 0;
                $serials = normalizeSerials($_POST['serials'] ?? []);

                try {
                    $db->beginTransaction();

                    // Bildupload verarbeiten
                    $image_path = null;
                    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                        $upload_dir = '../uploads/';
                        if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        
                        $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                        $new_filename = uniqid() . '.' . $file_extension;
                        $target_path = $upload_dir . $new_filename;
                        
                        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                            $image_path = 'uploads/' . $new_filename;
                        }
                    }

                    $query = "INSERT INTO products (name, description, image_path, max_tickets_per_day, active) VALUES (?, ?, ?, ?, ?)";
                    $stmt = $db->prepare($query);
                    if ($stmt->execute([$name, $description, $image_path, $maxTickets, $active])) {
                        $product_id = $db->lastInsertId();
                        
                        // Seriennummern speichern
                        if (!empty($serials)) {
                            $serialQuery = "INSERT INTO product_serials (product_id, serial_number, status, is_active) VALUES (?, ?, 'available', 1)";
                            $serialStmt = $db->prepare($serialQuery);
                            foreach ($serials as $serial) {
                                if (!empty($serial)) {
                                    $serialStmt->execute([$product_id, $serial]);
                                }
                            }
                        }

                        $db->commit();
                        $message = 'Produkt erfolgreich erstellt';
                        $messageType = 'success';
                    }
                } catch (Exception $e) {
                    $db->rollBack();
                    $message = 'Fehler beim Erstellen: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'update':
                $id = $_POST['id'] ?? null;
                $name = $_POST['name'] ?? '';
                $description = $_POST['description'] ?? '';
                $maxTickets = (int)($_POST['maxTickets'] ?? 4);
                $active = isset($_POST['active']) ? 1 : 0;
                $serials = normalizeSerials($_POST['serials'] ?? []);

                try {
                    $db->beginTransaction();

                    // Bildupload verarbeiten
                    $image_path = null;
                    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                        $upload_dir = '../uploads/';
                        if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        
                        $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                        $new_filename = uniqid() . '.' . $file_extension;
                        $target_path = $upload_dir . $new_filename;
                        
                        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                            $image_path = 'uploads/' . $new_filename;
                        }
                    }

                    $query = "UPDATE products SET name = ?, description = ?, max_tickets_per_day = ?, active = ?";
                    $params = [$name, $description, $maxTickets, $active];
                    
                    if ($image_path) {
                        $query .= ", image_path = ?";
                        $params[] = $image_path;
                    }
                    
                    $query .= " WHERE id = ?";
                    $params[] = $id;
                    
                    $stmt = $db->prepare($query);
                    if ($stmt->execute($params)) {
                        $existingQuery = "SELECT ps.id, ps.serial_number, ps.status, ps.is_active,
                                                 EXISTS(
                                                     SELECT 1
                                                     FROM booking_serials bs
                                                     WHERE bs.serial_id = ps.id
                                                       AND bs.returned_at IS NULL
                                                 ) AS is_currently_issued
                                          FROM product_serials ps
                                          WHERE ps.product_id = ?";
                        $existingStmt = $db->prepare($existingQuery);
                        $existingStmt->execute([$id]);
                        $existingSerials = $existingStmt->fetchAll(PDO::FETCH_ASSOC);

                        $existingByNumber = [];
                        foreach ($existingSerials as $existingSerial) {
                            $existingByNumber[$existingSerial['serial_number']][] = $existingSerial;
                        }

                        $submittedSerials = array_fill_keys($serials, true);
                        $activateSerialStmt = $db->prepare("UPDATE product_serials SET is_active = 1, status = 'available' WHERE id = ?");
                        $deactivateSerialStmt = $db->prepare("UPDATE product_serials SET is_active = 0 WHERE id = ?");

                        foreach ($existingByNumber as $serialNumber => $serialRows) {
                            $serialShouldExist = isset($submittedSerials[$serialNumber]);
                            $retainedSerialId = null;

                            foreach ($serialRows as $serialRow) {
                                if ((int)$serialRow['is_currently_issued'] === 1) {
                                    $retainedSerialId = $serialRow['id'];
                                    break;
                                }
                            }

                            if ($serialShouldExist && $retainedSerialId === null) {
                                foreach ($serialRows as $serialRow) {
                                    if ((int)$serialRow['is_active'] === 1) {
                                        $retainedSerialId = $serialRow['id'];
                                        break;
                                    }
                                }
                            }

                            if ($serialShouldExist && $retainedSerialId === null) {
                                $retainedSerialId = $serialRows[0]['id'];
                            }

                            if ($serialShouldExist && $retainedSerialId !== null) {
                                foreach ($serialRows as $serialRow) {
                                    if ($serialRow['id'] !== $retainedSerialId) {
                                        continue;
                                    }

                                    if ((int)$serialRow['is_active'] === 0) {
                                        $activateSerialStmt->execute([$serialRow['id']]);
                                    }

                                    break;
                                }
                            }

                            foreach ($serialRows as $serialRow) {
                                $isCurrentlyIssued = (int)$serialRow['is_currently_issued'] === 1;
                                $isActive = (int)$serialRow['is_active'] === 1;

                                if (!$serialShouldExist) {
                                    if ($isCurrentlyIssued || !$isActive) {
                                        continue;
                                    }

                                    $deactivateSerialStmt->execute([$serialRow['id']]);
                                    continue;
                                }

                                if ($serialRow['id'] === $retainedSerialId || $isCurrentlyIssued || !$isActive) {
                                    continue;
                                }

                                $deactivateSerialStmt->execute([$serialRow['id']]);
                            }
                        }

                        $serialQuery = "INSERT INTO product_serials (product_id, serial_number, status, is_active) VALUES (?, ?, 'available', 1)";
                        $serialStmt = $db->prepare($serialQuery);

                        foreach ($serials as $serial) {
                            if (isset($existingByNumber[$serial])) {
                                continue;
                            }

                            $serialStmt->execute([$id, $serial]);
                        }

                        $db->commit();
                        $message = 'Produkt erfolgreich aktualisiert';
                        $messageType = 'success';
                    }
                } catch (Exception $e) {
                    $db->rollBack();
                    $message = 'Fehler beim Aktualisieren: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'delete':
                $id = $_POST['id'] ?? null;
                if ($id) {
                    // Stattdessen deaktivieren
                    $query = "UPDATE products SET active = 0 WHERE id = ?";
                    $stmt = $db->prepare($query);
                    if ($stmt->execute([$id])) {
                        $message = 'Produkt erfolgreich deaktiviert';
                        $messageType = 'success';
                    }
                }
                break;
        }
    }
}

// Produkte abrufen
$query = "SELECT p.*, 
          GROUP_CONCAT(DISTINCT CASE WHEN ps.status = 'available' THEN ps.serial_number END ORDER BY ps.serial_number SEPARATOR ',') as serial_numbers,
          COUNT(DISTINCT ps.serial_number) as total_serials,
          COUNT(DISTINCT CASE WHEN ps.status = 'available' THEN ps.serial_number END) as available_serials
          FROM products p 
          LEFT JOIN product_serials ps ON p.id = ps.product_id AND ps.is_active = 1
          GROUP BY p.id
          ORDER BY p.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produktverwaltung</title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="../assets/css/dark-mode.css" rel="stylesheet">
</head>
<body>
    <?php include "../includes/admin_menu.php"; ?>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Produktverwaltung</h1>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#productModal">
                Neues Produkt
            </button>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Beschreibung</th>
                        <th>Max. Tickets/Tag</th>
                        <th>Seriennummern</th>
                        <th>Bild</th>
                        <th>Status</th>
                        <th>Erstellt am</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                            <td><?php echo htmlspecialchars($product['description']); ?></td>
                            <td><?php echo htmlspecialchars($product['max_tickets_per_day']); ?></td>
                            <td>
                                <?php 
                                echo $product['available_serials'] . ' verfügbar';
                                if ($product['total_serials'] > $product['available_serials']) {
                                    echo ' / ' . ($product['total_serials'] - $product['available_serials']) . ' in Verwendung';
                                }
                                ?>
                            </td>
                            <td>
                                <?php if ($product['image_path']): ?>
                                    <img src="../<?php echo htmlspecialchars($product['image_path']); ?>" 
                                         alt="Produktbild" style="max-width: 50px;">
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?php echo $product['active'] ? 'bg-success' : 'bg-danger'; ?>">
                                    <?php echo $product['active'] ? 'Aktiv' : 'Inaktiv'; ?>
                                </span>
                            </td>
                            <td><?php echo date('d.m.Y H:i', strtotime($product['created_at'])); ?></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-primary edit-product" 
                                        data-product='<?php echo htmlspecialchars(json_encode($product), ENT_QUOTES, 'UTF-8'); ?>'
                                        data-bs-toggle="modal" data-bs-target="#productModal">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Wirklich deaktivieren?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $product['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Produkt Modal -->
    <div class="modal fade" id="productModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Produkt</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="productForm" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="create">
                        <input type="hidden" name="id" id="productId">
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Name</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Beschreibung</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="maxTickets" class="form-label">Maximale Tickets pro Tag</label>
                            <input type="number" class="form-control" id="maxTickets" name="maxTickets" 
                                   min="1" max="100" value="4" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="image" class="form-label">Bild</label>
                            <input type="file" class="form-control" id="image" name="image" accept="image/*">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Seriennummern</label>
                            <div id="serialNumbers">
                                <div class="input-group mb-2">
                                    <input type="text" class="form-control" name="serials[]" placeholder="Seriennummer eingeben">
                                    <button type="button" class="btn btn-success add-serial">
                                        <i class="bi bi-plus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="active" name="active" checked>
                            <label class="form-check-label" for="active">Aktiv</label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Speichern</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/dark-mode.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const productModal = document.getElementById('productModal');
            const productForm = document.getElementById('productForm');
            const serialContainer = document.getElementById('serialNumbers');
            
            // Event-Handler für "Seriennummer hinzufügen" Button
            serialContainer.addEventListener('click', function(e) {
                if (e.target.closest('.add-serial')) {
                    const div = document.createElement('div');
                    div.className = 'input-group mb-2';
                    div.innerHTML = `
                        <input type="text" class="form-control" name="serials[]" placeholder="Seriennummer eingeben">
                        <button type="button" class="btn btn-danger remove-serial">
                            <i class="bi bi-trash"></i>
                        </button>
                    `;
                    serialContainer.appendChild(div);
                }
                
                if (e.target.closest('.remove-serial')) {
                    e.target.closest('.input-group').remove();
                }
            });
            
            // Modal Event-Handler
            productModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const isEdit = button.classList.contains('edit-product');
                
                if (isEdit) {
                    const product = JSON.parse(button.dataset.product);
                    productForm.elements['action'].value = 'update';
                    productForm.elements['id'].value = product.id;
                    productForm.elements['name'].value = product.name;
                    productForm.elements['description'].value = product.description;
                    productForm.elements['maxTickets'].value = product.max_tickets_per_day;
                    productForm.elements['active'].checked = product.active === "1";

                    // Seriennummern-Felder erstellen
                    serialContainer.innerHTML = ''; // Bestehende Felder löschen
                    
                    const serials = product.serial_numbers ? product.serial_numbers.split(',') : [];
                    if (serials.length > 0) {
                        serials.forEach((serial, index) => {
                            if (serial) {
                                const div = document.createElement('div');
                                div.className = 'input-group mb-2';
                                div.innerHTML = `
                                    <input type="text" class="form-control" name="serials[]" value="${serial}" placeholder="Seriennummer eingeben">
                                    <button type="button" class="btn btn-danger remove-serial">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                `;
                                serialContainer.appendChild(div);
                            }
                        });
                    }
                    
                    // Zusätzliches leeres Feld für neue Seriennummer
                    const div = document.createElement('div');
                    div.className = 'input-group mb-2';
                    div.innerHTML = `
                        <input type="text" class="form-control" name="serials[]" placeholder="Seriennummer eingeben">
                        <button type="button" class="btn btn-success add-serial">
                            <i class="bi bi-plus"></i>
                        </button>
                    `;
                    serialContainer.appendChild(div);
                } else {
                    productForm.reset();
                    productForm.elements['action'].value = 'create';
                    productForm.elements['id'].value = '';
                    serialContainer.innerHTML = `
                        <div class="input-group mb-2">
                            <input type="text" class="form-control" name="serials[]" placeholder="Seriennummer eingeben">
                            <button type="button" class="btn btn-success add-serial">
                                <i class="bi bi-plus"></i>
                            </button>
                        </div>
                    `;
                }
            });
        });
    </script>
</body>
</html>