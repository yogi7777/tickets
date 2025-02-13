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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $username = $_POST['username'] ?? '';
                $email = $_POST['email'] ?? '';
                $password = $_POST['password'] ?? '';
                $password_confirm = $_POST['password_confirm'] ?? '';

                // Validierung
                if ($password !== $password_confirm) {
                    $message = 'Die Passwörter stimmen nicht überein';
                    $messageType = 'danger';
                } else {
                    // Prüfen ob Benutzer bereits existiert
                    $query = "SELECT id FROM admins WHERE username = ? OR email = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$username, $email]);
                    
                    if ($stmt->rowCount() > 0) {
                        $message = 'Benutzername oder E-Mail bereits vergeben';
                        $messageType = 'danger';
                    } else {
                        // Benutzer erstellen
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $query = "INSERT INTO admins (username, email, password) VALUES (?, ?, ?)";
                        $stmt = $db->prepare($query);
                        
                        if ($stmt->execute([$username, $email, $hashed_password])) {
                            $message = 'Admin-Benutzer erfolgreich erstellt';
                            $messageType = 'success';
                        } else {
                            $message = 'Fehler beim Erstellen des Benutzers';
                            $messageType = 'danger';
                        }
                    }
                }
                break;

            case 'delete':
                $id = $_POST['id'] ?? null;
                // Verhindern, dass der letzte Admin gelöscht wird
                $query = "SELECT COUNT(*) as admin_count FROM admins";
                $stmt = $db->prepare($query);
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result['admin_count'] <= 1) {
                    $message = 'Der letzte Admin-Benutzer kann nicht gelöscht werden';
                    $messageType = 'danger';
                } else {
                    $query = "DELETE FROM admins WHERE id = ?";
                    $stmt = $db->prepare($query);
                    if ($stmt->execute([$id])) {
                        $message = 'Admin-Benutzer erfolgreich gelöscht';
                        $messageType = 'success';
                    }
                }
                break;
        }
    }
}

// Admins abrufen
$query = "SELECT id, username, email, created_at FROM admins ORDER BY created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <title>Admin-Benutzerverwaltung</title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/admin_menu.css" rel="stylesheet">
    <link href="../assets/css/dark-mode.css" rel="stylesheet">
</head>
<body>
<?php include "../includes/admin_menu.php"; ?>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Admin-Benutzerverwaltung</h1>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal">
                Neuer Admin
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
                        <th>Benutzername</th>
                        <th>E-Mail</th>
                        <th>Erstellt am</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($admins as $admin): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($admin['username']); ?></td>
                            <td><?php echo htmlspecialchars($admin['email']); ?></td>
                            <td><?php echo date('d.m.Y H:i', strtotime($admin['created_at'])); ?></td>
                            <td>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Admin-Benutzer wirklich löschen?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $admin['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Löschen</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Admin Modal -->
    <div class="modal fade" id="userModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Neuer Admin-Benutzer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="userForm" method="POST">
                        <input type="hidden" name="action" value="create">
                        
                        <div class="mb-3">
                            <label for="username" class="form-label">Benutzername</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">E-Mail</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Passwort</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password_confirm" class="form-label">Passwort bestätigen</label>
                            <input type="password" class="form-control" id="password_confirm" name="password_confirm" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Speichern</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/dark-mode.js"></script>
</body>
</html>
