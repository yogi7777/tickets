<?php
// admin/index.php
require_once '../config/database.php';
require_once '../includes/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

// Überprüfe Login
$auth->requireLogin();

// Hole Statistiken
$stats = array(
    'total_bookings' => 0,
    'today_bookings' => 0,
    'active_products' => 0
);

// Gesamtbuchungen
$query = "SELECT COUNT(*) as total FROM bookings WHERE booking_status = 'active'";
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$stats['total_bookings'] = $result['total'];

// Heutige Buchungen
$query = "SELECT COUNT(*) as today FROM bookings WHERE DATE(created_at) = CURDATE()";
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$stats['today_bookings'] = $result['today'];

// Aktive Produkte
$query = "SELECT COUNT(*) as active FROM products WHERE active = 1";
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$stats['active_products'] = $result['active'];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <script>
        // Theme aus Cookie oder System-Einstellung ermitteln
        (function() {
            function getTheme() {
                const theme = document.cookie.split('; ').find(row => row.startsWith('theme='))?.split('=')[1] || 'auto';
                if (theme === 'auto') {
                    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                }
                return theme;
            }
            document.documentElement.setAttribute('data-bs-theme', getTheme());
        })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/admin_menu.css" rel="stylesheet">
    <link href="../assets/css/dark-mode.css" rel="stylesheet">
</head>
<body>
    <?php include "../includes/admin_menu.php"; ?>

    <div class="container mt-4">
        <h1 class="mb-4">Dashboard</h1>
        
        <div class="row">
            <div class="col-md-4">
                <div class="card text-white bg-primary mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Aktive Buchungen</h5>
                        <p class="card-text display-4"><?php echo $stats['total_bookings']; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card text-white bg-success mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Heutige Buchungen</h5>
                        <p class="card-text display-4"><?php echo $stats['today_bookings']; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card text-white bg-info mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Aktive Produkte</h5>
                        <p class="card-text display-4"><?php echo $stats['active_products']; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Aktuelle Buchungen Tabelle -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0"><a href="bookings.php">Aktuelle Buchungen</a></h5>
            </div>
            <div class="card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Datum</th>
                            <th>Name</th>
                            <th>Produkt</th>
                            <th>Tickets</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $query = "SELECT b.*, p.name as product_name 
                                 FROM bookings b 
                                 JOIN products p ON b.product_id = p.id 
                                 ORDER BY b.created_at DESC 
                                 LIMIT 5";
                        $stmt = $db->prepare($query);
                        $stmt->execute();
                        
                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($row['booking_date']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['customer_firstname'] . ' ' . $row['customer_lastname']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['product_name']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['number_of_tickets']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['booking_status']) . "</td>";
                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/dark-mode.js"></script>
</body>
</html>
