<?php
include_once 'config/database.php';
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
    <title>Ticket Buchung</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/main.min.css" rel="stylesheet">
    <link href="assets/css/dark-mode.css" rel="stylesheet">
    <style>
        .product-card {
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .product-card.selected {
            border-color: #0d6efd;
            background-color: rgba(13, 110, 253, 0.05);
        }

        .product-image {
            width: 100px;
            height: 100px;
            object-fit: cover;
            display: block;
            margin: 0 auto;
            border-radius: 4px;
        }

        .card-text {
            font-size: 0.8rem;
            color: #6c757d;
            margin-bottom: 0;
        }

        .card-title {
            font-size: 1rem;
            margin-bottom: 0.5rem;
            min-height: 2.5rem;
        }

        .calendar-container {
            height: 500px;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col-md-8">
                    <h1 class="mb-4 text-center">Ticket Buchung</h1>
            </div>
        </div>
        
        <div class="row mb-4">
            <!-- Produkt Kacheln -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Produkt auswählen</h5>
                        <div class="row" id="productGrid">
                            <?php
                            $database = new Database();
                            $db = $database->getConnection();
                            
                            $query = "SELECT id, name, description, image_path FROM products WHERE active = 1";
                            $stmt = $db->prepare($query);
                            $stmt->execute();
                            
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                $short_description = strlen($row['description']) > 100 ? 
                                    substr($row['description'], 0, 100) . '...' : 
                                    $row['description'];
                                ?>
                                <div class="col-md-3 mb-3">
                                    <div class="card product-card h-100" data-product-id="<?php echo $row['id']; ?>">
                                        <div class="card-body">
                                            <h6 class="card-title"><?php echo htmlspecialchars($row['name']); ?></h6>
                                            <?php if ($row['image_path']): ?>
                                                <img src="<?php echo htmlspecialchars($row['image_path']); ?>" 
                                                     class="product-image mb-2" 
                                                     alt="<?php echo htmlspecialchars($row['name']); ?>">
                                            <?php endif; ?>
                                            <p class="card-text small"><?php echo htmlspecialchars($short_description); ?></p>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kalender -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card h-100">
                    <div class="card-body">
                        <div id="calendar" style="touch-action: manipulation;"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Buchungsformular -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Buchungsdetails</h5>
                        <form id="bookingForm" class="needs-validation" novalidate>
                            <input type="hidden" id="selectedDate" name="selectedDate">
                            <input type="hidden" id="productId" name="productId">
                            
                            <div class="mb-3">
                                <label for="firstName" class="form-label">Vorname</label>
                                <input type="text" class="form-control" id="firstName" name="firstName" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="lastName" class="form-label">Nachname</label>
                                <input type="text" class="form-control" id="lastName" name="lastName" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">E-Mail</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="ticketCount" class="form-label">Anzahl Tickets</label>
                                <select class="form-select" id="ticketCount" name="ticketCount" required>
                                    <option value="">Bitte wählen...</option>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Jetzt buchen</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/jquery-3.6.0.min.js"></script>
    <script src="assets/js/index.global.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const calendarEl = document.getElementById('calendar');
            let selectedDate = null; // Variable für das ausgewählte Datum

            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                height: 'auto',
                selectable: true,
                selectMirror: true,
                longPressDelay: 100,
                selectConstraint: {
                    start: new Date(),
                    end: new Date(new Date().setFullYear(new Date().getFullYear() + 1))
                },
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: ''
                },
                locale: 'de',
                firstDay: 1,
                select: function(info) {
                    selectedDate = info.startStr;  // Speichern des ausgewählten Datums
                    handleDateSelection(selectedDate);
                },
                selectAllow: function(selectInfo) {
                    return selectInfo.start >= new Date();
                },
                unselect: function() {
                    // Verhindern dass die Auswahl aufgehoben wird
                    if (selectedDate) {
                        calendar.select(selectedDate);
                    }
                }
            });
            
            calendar.render();

            // Produkt-Karten Handling
            const productCards = document.querySelectorAll('.product-card');
            
            productCards.forEach(card => {
                card.addEventListener('click', function() {
                    // Alle Karten deselektieren
                    productCards.forEach(c => c.classList.remove('selected'));
                    // Aktuelle Karte selektieren
                    this.classList.add('selected');
                    
                    const productId = this.dataset.productId;
                    // Hidden Input aktualisieren
                    document.getElementById('productId').value = productId;
                    
                    // Kalender aktualisieren
                    updateCalendarAvailability(productId);
                    
                    console.log('Produkt ausgewählt:', productId); // Debug
                });
            });

            // Buchungsformular Handler
            $('#bookingForm').submit(function(e) {
                e.preventDefault();
                const selectedProductId = $('#productId').val();
                
                console.log('Submit - Produkt ID:', selectedProductId); // Debug
                
                if (!selectedProductId) {
                    alert('Bitte wählen Sie zuerst ein Produkt aus.');
                    return false;
                }
                
                if (this.checkValidity()) {
                    submitBooking();
                }
                $(this).addClass('was-validated');
            });

            function handleDateSelection(date) {
                const productId = $('#productId').val();
                if (!productId) {
                    alert('Bitte wählen Sie zuerst ein Produkt aus.');
                    return;
                }
                $('#selectedDate').val(date);
                checkAvailability(productId, date);
            }

            function updateCalendarAvailability(productId) {
                $.get(`api/index.php/available-dates/${productId}`, function(data) {
                    calendar.removeAllEvents();
                    calendar.addEventSource(data);
                });
            }

            function checkAvailability(productId, date) {
                $.get(`api/index.php/available-tickets/${productId}/${date}`, function(data) {
                    const availableTickets = data.available;
                    updateTicketCountOptions(availableTickets);
                });
            }

            function updateTicketCountOptions(availableTickets) {
                const select = $('#ticketCount');
                const currentValue = select.val(); // Speichern des aktuellen Werts
                select.empty();
                select.append('<option value="">Bitte wählen...</option>');
                for (let i = 1; i <= Math.min(4, availableTickets); i++) {
                    select.append(`<option value="${i}">${i}</option>`);
                }
                if (currentValue && currentValue <= availableTickets) {
                    select.val(currentValue); // Wiederherstellen des vorherigen Werts
                }
            }

            function submitBooking() {
                const formData = {
                    productId: $('#productId').val(),
                    date: $('#selectedDate').val(),
                    firstName: $('#firstName').val(),
                    lastName: $('#lastName').val(),
                    email: $('#email').val(),
                    ticketCount: $('#ticketCount').val()
                };

                console.log('Sende Buchungsdaten:', formData);

                $.ajax({
                    url: 'api/index.php/book',
                    method: 'POST',
                    data: formData,
                    success: function(response) {
                        console.log('Server-Antwort:', response);
                        if (response.success) {
                            window.location.href = 'booking-success.php';
                        } else {
                            alert('Fehler bei der Buchung: ' + (response.error || 'Unbekannter Fehler'));
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Ajax-Fehler:', status, error);
                        console.error('Server-Antwort:', xhr.responseText);
                        try {
                            const response = JSON.parse(xhr.responseText);
                            alert('Fehler bei der Buchung: ' + (response.error || error));
                        } catch(e) {
                            alert('Fehler bei der Buchung: ' + error);
                        }
                    }
                });
            }
        });
    </script>
    <script src="assets/js/dark-mode.js"></script>
    <button id="theme-toggle" class="btn btn-link nav-link" onclick="window.darkMode.toggleTheme()">⚙️</button>
</body>
</html>
