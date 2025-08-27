-- Hotel PMS Database Schema
-- Training System for Hospitality Management

-- Create database
CREATE DATABASE IF NOT EXISTS hotel_pms;
USE hotel_pms;

-- Users table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE,
    role ENUM('front_desk', 'housekeeping', 'manager') NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Rooms table
CREATE TABLE rooms (
    id INT PRIMARY KEY AUTO_INCREMENT,
    room_number VARCHAR(10) UNIQUE NOT NULL,
    room_type ENUM('standard', 'deluxe', 'suite', 'presidential') NOT NULL,
    floor INT NOT NULL,
    capacity INT NOT NULL,
    rate DECIMAL(10,2) NOT NULL,
    status ENUM('available', 'occupied', 'reserved', 'maintenance', 'out_of_service') DEFAULT 'available',
    housekeeping_status ENUM('clean', 'dirty', 'cleaning', 'maintenance') DEFAULT 'clean',
    amenities TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Guests table
CREATE TABLE guests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    id_type ENUM('passport', 'driver_license', 'national_id', 'other') NOT NULL,
    id_number VARCHAR(50) NOT NULL,
    date_of_birth DATE,
    nationality VARCHAR(50),
    is_vip BOOLEAN DEFAULT FALSE,
    preferences TEXT,
    service_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Reservations table
CREATE TABLE reservations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    reservation_number VARCHAR(20) UNIQUE NOT NULL,
    guest_id INT NOT NULL,
    room_id INT NOT NULL,
    check_in_date DATE NOT NULL,
    check_out_date DATE NOT NULL,
    adults INT NOT NULL DEFAULT 1,
    children INT DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL,
    special_requests TEXT,
    booking_source ENUM('walk_in', 'online', 'phone', 'travel_agent') DEFAULT 'walk_in',
    status ENUM('confirmed', 'checked_in', 'checked_out', 'cancelled', 'no_show', 'walked') DEFAULT 'confirmed',
    checked_in_at TIMESTAMP NULL,
    checked_in_by INT NULL,
    checked_out_at TIMESTAMP NULL,
    checked_out_by INT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (guest_id) REFERENCES guests(id),
    FOREIGN KEY (room_id) REFERENCES rooms(id),
    FOREIGN KEY (checked_in_by) REFERENCES users(id),
    FOREIGN KEY (checked_out_by) REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Check-ins table
CREATE TABLE check_ins (
    id INT PRIMARY KEY AUTO_INCREMENT,
    reservation_id INT NOT NULL,
    room_key_issued BOOLEAN DEFAULT FALSE,
    welcome_amenities BOOLEAN DEFAULT FALSE,
    checked_in_by INT NOT NULL,
    checked_in_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id),
    FOREIGN KEY (checked_in_by) REFERENCES users(id)
);

-- Billing table
CREATE TABLE billing (
    id INT PRIMARY KEY AUTO_INCREMENT,
    reservation_id INT NOT NULL,
    guest_id INT NOT NULL,
    room_charges DECIMAL(10,2) DEFAULT 0,
    additional_charges DECIMAL(10,2) DEFAULT 0,
    tax_amount DECIMAL(10,2) DEFAULT 0,
    discount_amount DECIMAL(10,2) DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL,
    payment_status ENUM('pending', 'partial', 'paid', 'refunded') DEFAULT 'pending',
    payment_method ENUM('cash', 'credit_card', 'debit_card', 'bank_transfer', 'voucher') NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id),
    FOREIGN KEY (guest_id) REFERENCES guests(id)
);

-- Additional services table
CREATE TABLE additional_services (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    category ENUM('food_beverage', 'laundry', 'spa', 'transportation', 'other') NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Service charges table
CREATE TABLE service_charges (
    id INT PRIMARY KEY AUTO_INCREMENT,
    reservation_id INT NOT NULL,
    service_id INT NOT NULL,
    quantity INT DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    notes TEXT,
    charged_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id),
    FOREIGN KEY (service_id) REFERENCES additional_services(id),
    FOREIGN KEY (charged_by) REFERENCES users(id)
);

-- Group bookings table
CREATE TABLE group_bookings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    group_name VARCHAR(100) NOT NULL,
    group_size INT NOT NULL,
    group_discount DECIMAL(5,2) DEFAULT 10.00,
    reservation_id INT NOT NULL,
    guest_name VARCHAR(100) NOT NULL,
    room_number VARCHAR(10) NOT NULL,
    discount_amount DECIMAL(10,2) DEFAULT 0,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Housekeeping tasks table
CREATE TABLE housekeeping_tasks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    room_id INT NOT NULL,
    task_type ENUM('daily_cleaning', 'turn_down', 'deep_cleaning', 'maintenance', 'inspection') NOT NULL,
    status ENUM('pending', 'in_progress', 'completed', 'verified') DEFAULT 'pending',
    assigned_to INT NULL,
    scheduled_time DATETIME,
    completed_time DATETIME NULL,
    notes TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id),
    FOREIGN KEY (assigned_to) REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Maintenance requests table
CREATE TABLE maintenance_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    room_id INT NOT NULL,
    issue_type ENUM('plumbing', 'electrical', 'hvac', 'furniture', 'appliance', 'other') NOT NULL,
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    description TEXT NOT NULL,
    status ENUM('reported', 'assigned', 'in_progress', 'completed', 'verified') DEFAULT 'reported',
    reported_by INT NOT NULL,
    assigned_to INT NULL,
    estimated_cost DECIMAL(10,2) NULL,
    actual_cost DECIMAL(10,2) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id),
    FOREIGN KEY (reported_by) REFERENCES users(id),
    FOREIGN KEY (assigned_to) REFERENCES users(id)
);

-- Guest feedback table
CREATE TABLE guest_feedback (
    id INT PRIMARY KEY AUTO_INCREMENT,
    reservation_id INT NOT NULL,
    guest_id INT NOT NULL,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    feedback_type ENUM('compliment', 'complaint', 'suggestion', 'general') NOT NULL,
    category ENUM('service', 'cleanliness', 'facilities', 'staff', 'food', 'other') NOT NULL,
    comments TEXT,
    is_resolved BOOLEAN DEFAULT FALSE,
    resolved_by INT NULL,
    resolution_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id),
    FOREIGN KEY (guest_id) REFERENCES guests(id),
    FOREIGN KEY (resolved_by) REFERENCES users(id)
);

-- Activity logs table
CREATE TABLE activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Notifications table
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Inventory table
CREATE TABLE inventory (
    id INT PRIMARY KEY AUTO_INCREMENT,
    item_name VARCHAR(100) NOT NULL,
    category ENUM('linens', 'amenities', 'cleaning_supplies', 'maintenance', 'food_beverage') NOT NULL,
    description TEXT,
    quantity INT NOT NULL DEFAULT 0,
    unit VARCHAR(20) NOT NULL,
    reorder_level INT NOT NULL DEFAULT 10,
    unit_cost DECIMAL(10,2) NOT NULL,
    supplier VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Inventory transactions table
CREATE TABLE inventory_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    item_id INT NOT NULL,
    transaction_type ENUM('in', 'out', 'adjustment') NOT NULL,
    quantity INT NOT NULL,
    reason TEXT,
    performed_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES inventory(id),
    FOREIGN KEY (performed_by) REFERENCES users(id)
);

-- Maintenance requests table
CREATE TABLE maintenance_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    room_id INT NOT NULL,
    request_type ENUM('maintenance', 'housekeeping', 'concierge', 'technical') NOT NULL,
    description TEXT NOT NULL,
    special_instructions TEXT,
    status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Service charges table
CREATE TABLE service_charges (
    id INT PRIMARY KEY AUTO_INCREMENT,
    reservation_id INT NOT NULL,
    service_category ENUM('minibar', 'laundry', 'spa', 'restaurant', 'transportation', 'other') NOT NULL,
    service_name VARCHAR(100) NOT NULL,
    quantity INT DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    description TEXT,
    status ENUM('pending', 'billed', 'paid') DEFAULT 'pending',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Bills table
CREATE TABLE bills (
    id INT PRIMARY KEY AUTO_INCREMENT,
    bill_number VARCHAR(20) UNIQUE NOT NULL,
    reservation_id INT NOT NULL,
    bill_date DATE NOT NULL,
    due_date DATE NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    tax_amount DECIMAL(10,2) NOT NULL,
    discount_amount DECIMAL(10,2) DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'paid', 'overdue', 'cancelled') DEFAULT 'pending',
    notes TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Bill items table
CREATE TABLE bill_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    bill_id INT NOT NULL,
    description VARCHAR(200) NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (bill_id) REFERENCES bills(id)
);

-- Payments table
CREATE TABLE payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    payment_number VARCHAR(20) UNIQUE NOT NULL,
    bill_id INT NOT NULL,
    payment_method ENUM('cash', 'credit_card', 'debit_card', 'bank_transfer', 'check') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reference_number VARCHAR(50),
    notes TEXT,
    processed_by INT NOT NULL,
    FOREIGN KEY (bill_id) REFERENCES bills(id),
    FOREIGN KEY (processed_by) REFERENCES users(id)
);

-- Discounts table
CREATE TABLE discounts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    bill_id INT NOT NULL,
    discount_type ENUM('percentage', 'fixed', 'loyalty', 'promotional') NOT NULL,
    discount_value DECIMAL(10,2) NOT NULL,
    discount_amount DECIMAL(10,2) NOT NULL,
    reason VARCHAR(200),
    description TEXT,
    applied_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bill_id) REFERENCES bills(id),
    FOREIGN KEY (applied_by) REFERENCES users(id)
);

-- Vouchers table
CREATE TABLE vouchers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    voucher_code VARCHAR(20) UNIQUE NOT NULL,
    voucher_type ENUM('percentage', 'fixed', 'free_night', 'upgrade') NOT NULL,
    voucher_value DECIMAL(10,2) NOT NULL,
    usage_limit INT DEFAULT 1,
    valid_from DATE NOT NULL,
    valid_until DATE NOT NULL,
    description TEXT,
    status ENUM('active', 'used', 'expired') DEFAULT 'active',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Voucher usage table
CREATE TABLE voucher_usage (
    id INT PRIMARY KEY AUTO_INCREMENT,
    voucher_id INT NOT NULL,
    bill_id INT NOT NULL,
    used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    used_by INT NOT NULL,
    FOREIGN KEY (voucher_id) REFERENCES vouchers(id),
    FOREIGN KEY (bill_id) REFERENCES bills(id),
    FOREIGN KEY (used_by) REFERENCES users(id)
);

-- Loyalty points table
CREATE TABLE loyalty_points (
    id INT PRIMARY KEY AUTO_INCREMENT,
    guest_id INT NOT NULL,
    action ENUM('earn', 'redeem', 'adjust') NOT NULL,
    points INT NOT NULL,
    reason VARCHAR(200),
    description TEXT,
    processed_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (guest_id) REFERENCES guests(id),
    FOREIGN KEY (processed_by) REFERENCES users(id)
);

-- Inventory categories table
CREATE TABLE inventory_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Inventory items table
CREATE TABLE inventory_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    item_name VARCHAR(200) NOT NULL,
    category_id INT NOT NULL,
    current_stock INT NOT NULL DEFAULT 0,
    minimum_stock INT NOT NULL DEFAULT 0,
    unit_price DECIMAL(10,2) DEFAULT 0.00,
    description TEXT,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES inventory_categories(id)
);

-- Inventory transactions table
CREATE TABLE inventory_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    item_id INT NOT NULL,
    transaction_type ENUM('in', 'out', 'adjustment') NOT NULL,
    quantity INT NOT NULL,
    reason VARCHAR(200),
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    user_id INT NOT NULL,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Training scenarios table
CREATE TABLE training_scenarios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    instructions TEXT,
    category ENUM('front_desk', 'housekeeping', 'management') NOT NULL,
    difficulty ENUM('beginner', 'intermediate', 'advanced') NOT NULL,
    estimated_time INT NOT NULL DEFAULT 10,
    points INT NOT NULL DEFAULT 10,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Scenario questions table
CREATE TABLE scenario_questions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    scenario_id INT NOT NULL,
    question TEXT NOT NULL,
    question_order INT NOT NULL,
    correct_answer VARCHAR(10) NOT NULL,
    FOREIGN KEY (scenario_id) REFERENCES training_scenarios(id)
);

-- Question options table
CREATE TABLE question_options (
    id INT PRIMARY KEY AUTO_INCREMENT,
    question_id INT NOT NULL,
    option_text TEXT NOT NULL,
    option_value VARCHAR(10) NOT NULL,
    option_order INT NOT NULL,
    FOREIGN KEY (question_id) REFERENCES scenario_questions(id)
);

-- Customer service scenarios table
CREATE TABLE customer_service_scenarios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    situation TEXT NOT NULL,
    guest_request TEXT NOT NULL,
    type ENUM('complaints', 'requests', 'emergencies') NOT NULL,
    difficulty ENUM('beginner', 'intermediate', 'advanced') NOT NULL,
    estimated_time INT NOT NULL DEFAULT 5,
    points INT NOT NULL DEFAULT 15,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Problem scenarios table
CREATE TABLE problem_scenarios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    resources TEXT,
    severity ENUM('low', 'medium', 'high', 'critical') NOT NULL,
    difficulty ENUM('beginner', 'intermediate', 'advanced') NOT NULL,
    time_limit INT NOT NULL DEFAULT 5,
    points INT NOT NULL DEFAULT 20,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Training attempts table
CREATE TABLE training_attempts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    scenario_id INT NOT NULL,
    scenario_type ENUM('scenario', 'customer_service', 'problem_solving') NOT NULL,
    answers JSON,
    score DECIMAL(5,2) NOT NULL DEFAULT 0,
    duration_minutes INT DEFAULT 0,
    status ENUM('in_progress', 'completed', 'abandoned') DEFAULT 'in_progress',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Training certificates table
CREATE TABLE training_certificates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    type ENUM('scenario', 'customer_service', 'problem_solving') NOT NULL,
    status ENUM('earned', 'expired') DEFAULT 'earned',
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Insert sample users
INSERT INTO users (name, username, password, email, role) VALUES
('John Smith', 'frontdesk1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'john@hotel.com', 'front_desk'),
('Maria Garcia', 'housekeeping1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'maria@hotel.com', 'housekeeping'),
('David Johnson', 'manager1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'david@hotel.com', 'manager');

-- Insert sample rooms
INSERT INTO rooms (room_number, room_type, floor, capacity, rate, status, housekeeping_status) VALUES
('101', 'standard', 1, 2, 150.00, 'available'),
('102', 'standard', 1, 2, 150.00, 'available'),
('201', 'deluxe', 2, 3, 250.00, 'available'),
('202', 'deluxe', 2, 3, 250.00, 'available'),
('301', 'suite', 3, 4, 400.00, 'available'),
('302', 'suite', 3, 4, 400.00, 'available'),
('401', 'presidential', 4, 6, 800.00, 'available');

-- Insert sample additional services
INSERT INTO additional_services (name, description, price, category) VALUES
('Room Service Breakfast', 'Continental breakfast delivered to room', 25.00, 'food_beverage'),
('Laundry Service', 'Same day laundry service', 15.00, 'laundry'),
('Spa Treatment', 'Relaxing massage therapy', 80.00, 'spa'),
('Airport Transfer', 'Round trip airport transportation', 50.00, 'transportation'),
('Mini Bar Refill', 'Refill of mini bar items', 30.00, 'food_beverage');

-- Insert sample inventory
INSERT INTO inventory (item_name, category, description, quantity, unit, reorder_level, unit_cost, supplier) VALUES
('Towels', 'linens', 'Bath towels', 200, 'pieces', 50, 15.00, 'Linen Supply Co'),
('Bed Sheets', 'linens', 'King size bed sheets', 100, 'sets', 20, 45.00, 'Linen Supply Co'),
('Shampoo', 'amenities', 'Hotel brand shampoo', 500, 'bottles', 100, 2.50, 'Amenity Supply'),
('Soap', 'amenities', 'Hotel brand soap', 500, 'bars', 100, 1.50, 'Amenity Supply'),
('Cleaning Solution', 'cleaning_supplies', 'Multi-purpose cleaner', 50, 'bottles', 10, 8.00, 'Cleaning Supply Co');
