document.addEventListener('DOMContentLoaded', function() {
    let calendar;
    const calendarEl = document.getElementById('calendar');
    
    // Kalender Initialisierung
    calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        selectable: true,
        locale: 'de',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth'
        },
        select: function(info) {
            handleDateSelection(info.startStr);
        },
        validRange: function(nowDate) {
            return {
                start: nowDate,
                end: new Date(nowDate.getFullYear() + 1, nowDate.getMonth(), nowDate.getDate())
            };
        }
    });
    
    calendar.render();

    // Produkt Auswahl Handler
    $('#productSelect').change(function() {
        const productId = $(this).val();
        if (productId) {
            $('#productId').val(productId);
            fetchProductDetails(productId);
            updateCalendarAvailability(productId);
        }
    });

    // Buchungsformular Handler
    $('#bookingForm').submit(function(e) {
        e.preventDefault();
        if (this.checkValidity()) {
            submitBooking();
        }
        $(this).addClass('was-validated');
    });

    // Funktionen
    function handleDateSelection(date) {
        const productId = $('#productId').val();
        if (!productId) {
            alert('Bitte wählen Sie zuerst ein Produkt aus.');
            return;
        }
        $('#selectedDate').val(date);
        checkAvailability(productId, date);
    }

    function fetchProductDetails(productId) {
        $.get(`api/index.php/product/${productId}`, function(data) {
            $('#productDescription').html(data.description);
        });
    }

    function updateCalendarAvailability(productId) {
        $.get(`api/index.php/available-dates/${productId}`, function(data) {
            calendar.removeAllEventSources();
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
        select.empty();
        select.append('<option value="">Bitte wählen...</option>');
        for (let i = 1; i <= Math.min(4, availableTickets); i++) {
            select.append(`<option value="${i}">${i}</option>`);
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

        $.post('api/index.php/book', formData, function(response) {
            if (response.success) {
                alert('Buchung erfolgreich! Sie erhalten eine Bestätigungs-E-Mail.');
                $('#bookingForm')[0].reset();
                $('#bookingForm').removeClass('was-validated');
                updateCalendarAvailability($('#productId').val());
            } else {
                alert('Fehler bei der Buchung: ' + response.message);
            }
        });
    }
});
