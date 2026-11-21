USE bakery_reservation_system;

CREATE TABLE IF NOT EXISTS reservation (
    reservation_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    phone VARCHAR(20) NOT NULL,
    outlet VARCHAR(150) NOT NULL,
    reservation_date DATE NOT NULL,
    reservation_time TIME NOT NULL,
    guests TINYINT UNSIGNED NOT NULL,
    table_name VARCHAR(20) NOT NULL,
    special_request TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'Pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reservation_customer
        FOREIGN KEY (customer_id) REFERENCES customer(customer_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT uq_reservation_slot
        UNIQUE (outlet, reservation_date, reservation_time, table_name),
    INDEX idx_reservation_customer_date (customer_id, reservation_date)
) ENGINE=InnoDB;

SHOW TABLES LIKE 'reservation';
