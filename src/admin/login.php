<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$error = '';
$setup_mode = false;

// Prüfen ob es bereits Admin-Benutzer gibt
$query = "SELECT COUNT(*) as admin_count FROM admins";
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);

// Wenn keine Admins existieren und keine Setup-Sperre existiert
if ($result['admin_count'] == 0 && !file_exists('../config/setup.lock')) {
    $setup_mode = true;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'setup') {
        $username = $_POST['username'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';

        // Validierung
        if (empty($username) || empty($email) || empty($password)) {
            $error = 'Bitte alle Felder ausfüllen';
        } elseif ($password !== $password_confirm) {
            $error = 'Passwörter stimmen nicht überein';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Ungültige E-Mail-Adresse';
        } else {
            try {
                // Admin erstellen
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $query = "INSERT INTO admins (username, email, password) VALUES (?, ?, ?)";
                $stmt = $db->prepare($query);
                
                if ($stmt->execute([$username, $email, $hashed_password])) {
                    // Setup-Sperre erstellen
                    file_put_contents('../config/setup.lock', date('Y-m-d H:i:s'));
                    header('Location: login.php?setup=complete');
                    exit;
                } else {
                    $error = 'Fehler beim Erstellen des Admin-Benutzers';
                }
            } catch (Exception $e) {
                $error = 'Datenbankfehler: ' . $e->getMessage();
            }
        }
    }
} else {
    // Normaler Login-Prozess
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        if ($auth->login($username, $password)) {
            header('Location: index.php');
            exit();
        } else {
            $error = 'Ungültige Anmeldedaten';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $setup_mode ? 'System Setup' : 'Admin Login'; ?></title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/dark-mode.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="text-center">
                            <?php echo $setup_mode ? 'Initialer System Setup' : 'Admin Login'; ?>
                        </h3>
                    </div>
                    <div class="card-body">
                        <?php if (isset($_GET['setup']) && $_GET['setup'] === 'complete'): ?>
                            <div class="alert alert-success">
                                Setup erfolgreich abgeschlossen. Sie können sich nun anmelden.
                            </div>
                        <?php endif; ?>

                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($setup_mode): ?>
                            <!-- Setup Formular -->
                            <form method="POST">
                                <input type="hidden" name="action" value="setup">
                                
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
                                
                                <button type="submit" class="btn btn-primary w-100">Admin-Benutzer erstellen</button>
                            </form>
                        <?php else: ?>
                            <!-- Login Formular -->
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Benutzername</label>
                                    <input type="text" class="form-control" id="username" name="username" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="password" class="form-label">Passwort</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                                
                                <button type="submit" class="btn btn-primary w-100">Anmelden</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/dark-mode.js"></script>
</body>
</html>