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
    <title>Buchung erfolgreich</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/dark-mode.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body text-center">
                        <h1 class="card-title text-success mb-4">
                            <i class="bi bi-check-circle-fill"></i> Buchung erfolgreich!
                        </h1>
                        <p class="lead">
                            Vielen Dank für Ihre Buchung. Sie erhalten in Kürze eine Bestätigungs-E-Mail.
                        </p>
                        <hr>
                        <p>
                            Die Tickets können am Empfang abgeholt werden.
                        </p>
                        <div class="mt-4">
                            <a href="index.php" class="btn btn-primary">Zurück zur Startseite</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <button id="theme-toggle" class="btn btn-link nav-link" onclick="window.darkMode.toggleTheme()">⚙️</button>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/dark-mode.js"></script>
</body>
</html>
