<?php $current_page = basename($_SERVER['PHP_SELF']); ?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="#">Ticket System</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav">
            <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>" href="index.php">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'products.php') ? 'active' : ''; ?>" href="products.php">Produkte</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'bookings.php') ? 'active' : ''; ?>" href="bookings.php">Buchungen</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'users.php') ? 'active' : ''; ?>" href="users.php">Benutzer</a>
            </li>
        </ul>
        <ul class="navbar-nav ms-auto">
        <button id="theme-toggle" class="btn btn-link nav-link" onclick="window.darkMode.toggleTheme()">⚙️</button>
            <li class="nav-item">
                <a class="nav-link" href="logout.php">Abmelden</a>
            </li>
        </ul>
    </div>
</div>
</nav>
