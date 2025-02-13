-- Admins Tabelle
CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Produkte Tabelle
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    image_path VARCHAR(255),
    max_tickets_per_day INT NOT NULL DEFAULT 4,
    active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Buchungen Tabelle
CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    booking_date DATE NOT NULL,
    ticket_picked_up DATETIME DEFAULT NULL,
    ticket_returned DATETIME DEFAULT NULL,
    customer_firstname VARCHAR(50) NOT NULL,
    customer_lastname VARCHAR(50) NOT NULL,
    customer_email VARCHAR(100) NOT NULL,
    number_of_tickets INT NOT NULL,
    booking_status ENUM('active', 'cancelled', 'done') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id),
    -- Ensure unique combination of product and date for counting available tickets
    INDEX idx_product_date (product_id, booking_date)
);

-- Ticket Serialnumber
CREATE TABLE product_serials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    serial_number VARCHAR(100) NOT NULL,
    status ENUM('available', 'picked_up', 'returned') DEFAULT 'available',
    FOREIGN KEY (product_id) REFERENCES products(id)
);

CREATE TABLE booking_serials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    serial_id INT NOT NULL,
    picked_up_at DATETIME DEFAULT NULL,
    returned_at DATETIME DEFAULT NULL,
    FOREIGN KEY (booking_id) REFERENCES bookings(id),
    FOREIGN KEY (serial_id) REFERENCES product_serials(id)
);