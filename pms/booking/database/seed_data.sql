-- Hotel PMS Database Seeding Script
-- Comprehensive sample data with proper foreign key relationships

USE hotel_pms_clean;

-- Clear existing data (in reverse order of dependencies)
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE voucher_usage;
TRUNCATE TABLE loyalty_points;
TRUNCATE TABLE payments;
TRUNCATE TABLE bill_items;
TRUNCATE TABLE bills;
TRUNCATE TABLE discounts;
TRUNCATE TABLE vouchers;
TRUNCATE TABLE service_charges;
TRUNCATE TABLE maintenance_requests;
TRUNCATE TABLE housekeeping_tasks;
TRUNCATE TABLE group_bookings;
TRUNCATE TABLE guest_feedback;
TRUNCATE TABLE check_ins;
TRUNCATE TABLE billing;
TRUNCATE TABLE reservations;
TRUNCATE TABLE inventory_transactions;
TRUNCATE TABLE inventory_items;
TRUNCATE TABLE inventory_categories;
TRUNCATE TABLE inventory;
TRUNCATE TABLE notifications;
TRUNCATE TABLE activity_logs;
TRUNCATE TABLE training_certificates;
TRUNCATE TABLE training_attempts;
TRUNCATE TABLE question_options;
TRUNCATE TABLE scenario_questions;
TRUNCATE TABLE problem_scenarios;
TRUNCATE TABLE customer_service_scenarios;
TRUNCATE TABLE training_scenarios;
TRUNCATE TABLE guests;
TRUNCATE TABLE rooms;
TRUNCATE TABLE users;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. USERS (Base table - no dependencies)
INSERT INTO users (name, username, password, email, role, is_active) VALUES
('John Smith', 'frontdesk1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'john@hotel.com', 'front_desk', 1),
('Maria Garcia', 'housekeeping1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'maria@hotel.com', 'housekeeping', 1),
('David Johnson', 'manager1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'david@hotel.com', 'manager', 1),
('Sarah Wilson', 'frontdesk2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'sarah@hotel.com', 'front_desk', 1),
('Carlos Rodriguez', 'housekeeping2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'carlos@hotel.com', 'housekeeping', 1),
('Emily Chen', 'manager2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'emily@hotel.com', 'manager', 1);

-- 2. ROOMS (Base table - no dependencies)
INSERT INTO rooms (room_number, room_type, floor, capacity, rate, status, housekeeping_status, amenities) VALUES
('101', 'standard', 1, 2, 150.00, 'available', 'clean', 'WiFi, TV, Air Conditioning, Mini Fridge'),
('102', 'standard', 1, 2, 150.00, 'occupied', 'dirty', 'WiFi, TV, Air Conditioning, Mini Fridge'),
('103', 'standard', 1, 2, 150.00, 'maintenance', 'maintenance', 'WiFi, TV, Air Conditioning, Mini Fridge'),
('201', 'deluxe', 2, 3, 250.00, 'available', 'clean', 'WiFi, TV, Air Conditioning, Mini Bar, Balcony'),
('202', 'deluxe', 2, 3, 250.00, 'reserved', 'clean', 'WiFi, TV, Air Conditioning, Mini Bar, Balcony'),
('203', 'deluxe', 2, 3, 250.00, 'occupied', 'cleaning', 'WiFi, TV, Air Conditioning, Mini Bar, Balcony'),
('301', 'suite', 3, 4, 400.00, 'available', 'clean', 'WiFi, TV, Air Conditioning, Mini Bar, Living Room, Jacuzzi'),
('302', 'suite', 3, 4, 400.00, 'occupied', 'dirty', 'WiFi, TV, Air Conditioning, Mini Bar, Living Room, Jacuzzi'),
('401', 'presidential', 4, 6, 800.00, 'available', 'clean', 'WiFi, TV, Air Conditioning, Mini Bar, Living Room, Jacuzzi, Butler Service'),
('402', 'presidential', 4, 6, 800.00, 'out_of_service', 'maintenance', 'WiFi, TV, Air Conditioning, Mini Bar, Living Room, Jacuzzi, Butler Service');

-- 3. GUESTS (Base table - no dependencies)
INSERT INTO guests (first_name, last_name, email, phone, address, id_type, id_number, date_of_birth, nationality, is_vip, preferences, service_notes) VALUES
('Michael', 'Brown', 'michael.brown@email.com', '+1-555-0101', '123 Main St, New York, NY 10001', 'passport', 'US123456789', '1985-03-15', 'American', 0, 'Non-smoking room, High floor', 'Regular guest, prefers room service'),
('Jennifer', 'Davis', 'jennifer.davis@email.com', '+1-555-0102', '456 Oak Ave, Los Angeles, CA 90210', 'driver_license', 'CA987654321', '1990-07-22', 'American', 1, 'King bed, City view, Late check-in', 'VIP guest, comp upgrade available'),
('Robert', 'Wilson', 'robert.wilson@email.com', '+1-555-0103', '789 Pine Rd, Chicago, IL 60601', 'national_id', 'IL456789123', '1978-11-08', 'American', 0, 'Twin beds, Quiet room', 'Business traveler'),
('Lisa', 'Anderson', 'lisa.anderson@email.com', '+1-555-0104', '321 Elm St, Miami, FL 33101', 'passport', 'UK876543210', '1982-05-12', 'British', 1, 'Ocean view, Balcony, Early check-in', 'VIP guest, anniversary celebration'),
('James', 'Taylor', 'james.taylor@email.com', '+1-555-0105', '654 Maple Dr, Seattle, WA 98101', 'driver_license', 'WA234567890', '1988-09-30', 'American', 0, 'Mountain view, Non-smoking', 'Hiking enthusiast'),
('Amanda', 'Martinez', 'amanda.martinez@email.com', '+1-555-0106', '987 Cedar Ln, Austin, TX 73301', 'passport', 'MX345678901', '1992-01-25', 'Mexican', 0, 'Pool view, Connecting rooms', 'Family with children'),
('Christopher', 'Garcia', 'christopher.garcia@email.com', '+1-555-0107', '147 Birch Way, Denver, CO 80201', 'national_id', 'CO456789012', '1980-12-03', 'American', 1, 'Suite upgrade, Airport transfer', 'VIP guest, business conference'),
('Jessica', 'Rodriguez', 'jessica.rodriguez@email.com', '+1-555-0108', '258 Spruce Ct, Portland, OR 97201', 'driver_license', 'OR567890123', '1987-04-18', 'American', 0, 'Pet-friendly, Ground floor', 'Traveling with service dog'),
('Daniel', 'Lee', 'daniel.lee@email.com', '+1-555-0109', '369 Willow Pl, San Francisco, CA 94101', 'passport', 'CA678901234', '1983-08-07', 'American', 0, 'Bay view, High-speed internet', 'Tech conference attendee'),
('Nicole', 'White', 'nicole.white@email.com', '+1-555-0110', '741 Aspen St, Boston, MA 02101', 'national_id', 'MA789012345', '1989-06-14', 'American', 1, 'Historic district view, Wine service', 'VIP guest, wine enthusiast');

-- 4. RESERVATIONS (Depends on users, guests, rooms)
INSERT INTO reservations (reservation_number, guest_id, room_id, check_in_date, check_out_date, adults, children, total_amount, special_requests, booking_source, status, checked_in_at, checked_in_by, created_by) VALUES
('RES20241201001', 1, 2, '2024-12-01', '2024-12-03', 2, 0, 300.00, 'Late arrival after 10 PM', 'online', 'checked_in', '2024-12-01 22:30:00', 1, 1),
('RES20241201002', 2, 6, '2024-12-01', '2024-12-05', 2, 1, 1000.00, 'Anniversary celebration, champagne on arrival', 'phone', 'checked_in', '2024-12-01 15:00:00', 1, 1),
('RES20241202001', 3, 8, '2024-12-02', '2024-12-04', 1, 0, 800.00, 'Business trip, quiet room preferred', 'walk_in', 'checked_in', '2024-12-02 14:00:00', 4, 4),
('RES20241202002', 4, 5, '2024-12-02', '2024-12-06', 2, 0, 1000.00, 'Early check-in requested', 'travel_agent', 'confirmed', NULL, NULL, 1),
('RES20241203001', 5, 1, '2024-12-03', '2024-12-05', 1, 0, 300.00, 'Hiking gear storage needed', 'online', 'confirmed', NULL, NULL, 1),
('RES20241203002', 6, 7, '2024-12-03', '2024-12-07', 2, 2, 1600.00, 'Family with young children, extra towels needed', 'phone', 'confirmed', NULL, NULL, 4),
('RES20241204001', 7, 9, '2024-12-04', '2024-12-06', 2, 0, 1600.00, 'Business conference, high-speed internet essential', 'online', 'confirmed', NULL, NULL, 1),
('RES20241204002', 8, 3, '2024-12-04', '2024-12-08', 1, 0, 600.00, 'Service dog accommodation required', 'walk_in', 'confirmed', NULL, NULL, 4),
('RES20241205001', 9, 1, '2024-12-05', '2024-12-07', 1, 0, 300.00, 'Tech conference, quiet workspace needed', 'online', 'confirmed', NULL, NULL, 1),
('RES20241205002', 10, 9, '2024-12-05', '2024-12-09', 2, 0, 3200.00, 'Wine tasting weekend, wine fridge requested', 'travel_agent', 'confirmed', NULL, NULL, 1);

-- 5. CHECK-INS (Depends on reservations, users)
INSERT INTO check_ins (reservation_id, room_key_issued, welcome_amenities, checked_in_by, checked_in_at) VALUES
(1, 1, 1, 1, '2024-12-01 22:30:00'),
(2, 1, 1, 1, '2024-12-01 15:00:00'),
(3, 1, 0, 4, '2024-12-02 14:00:00');

-- 6. BILLING (Depends on reservations, guests)
INSERT INTO billing (reservation_id, guest_id, room_charges, additional_charges, tax_amount, discount_amount, total_amount, payment_status, payment_method) VALUES
(1, 1, 300.00, 25.00, 32.50, 0.00, 357.50, 'paid', 'credit_card'),
(2, 2, 1000.00, 80.00, 108.00, 50.00, 1138.00, 'paid', 'credit_card'),
(3, 3, 800.00, 0.00, 86.40, 0.00, 886.40, 'pending', NULL);

-- 7. ADDITIONAL SERVICES (Base table - no dependencies)
INSERT INTO additional_services (name, description, price, category, is_active) VALUES
('Room Service Breakfast', 'Continental breakfast delivered to room', 25.00, 'food_beverage', 1),
('Laundry Service', 'Same day laundry service', 15.00, 'laundry', 1),
('Spa Treatment', 'Relaxing massage therapy', 80.00, 'spa', 1),
('Airport Transfer', 'Round trip airport transportation', 50.00, 'transportation', 1),
('Mini Bar Refill', 'Refill of mini bar items', 30.00, 'food_beverage', 1),
('Champagne Service', 'Bottle of champagne with glasses', 45.00, 'food_beverage', 1),
('Pet Sitting Service', 'In-room pet care service', 20.00, 'other', 1),
('Concierge Service', 'Personal concierge assistance', 35.00, 'other', 1);

-- 8. SERVICE CHARGES (Depends on reservations, additional_services, users)
INSERT INTO service_charges (reservation_id, service_id, quantity, unit_price, total_price, notes, charged_by) VALUES
(1, 1, 1, 25.00, 25.00, 'Breakfast delivered at 8:00 AM', 1),
(2, 6, 1, 45.00, 45.00, 'Anniversary celebration', 1),
(2, 3, 1, 80.00, 80.00, 'Couples massage', 1);

-- 9. GROUP BOOKINGS (Depends on reservations, users)
INSERT INTO group_bookings (group_name, group_size, group_discount, reservation_id, guest_name, room_number, discount_amount, created_by) VALUES
('Tech Conference 2024', 15, 15.00, 7, 'Christopher Garcia', '401', 240.00, 1),
('Family Reunion', 8, 10.00, 6, 'Amanda Martinez', '301', 160.00, 4);

-- 10. HOUSEKEEPING TASKS (Depends on rooms, users)
INSERT INTO housekeeping_tasks (room_id, task_type, status, assigned_to, scheduled_time, completed_time, notes, created_by) VALUES
(2, 'daily_cleaning', 'completed', 2, '2024-12-01 09:00:00', '2024-12-01 10:30:00', 'Room cleaned, towels replaced', 1),
(6, 'turn_down', 'pending', 2, '2024-12-01 18:00:00', NULL, 'Evening turndown service', 1),
(8, 'deep_cleaning', 'in_progress', 5, '2024-12-01 08:00:00', NULL, 'Deep cleaning in progress', 1),
(3, 'maintenance', 'completed', 5, '2024-12-01 07:00:00', '2024-12-01 09:00:00', 'AC unit repaired', 1);

-- 11. MAINTENANCE REQUESTS (Depends on rooms, users)
INSERT INTO maintenance_requests (room_id, issue_type, priority, description, status, reported_by, assigned_to, estimated_cost, actual_cost) VALUES
(3, 'hvac', 'high', 'Air conditioning not working properly', 'completed', 1, 5, 150.00, 120.00),
(10, 'electrical', 'urgent', 'Power outlet not working', 'assigned', 4, 5, 75.00, NULL),
(2, 'plumbing', 'medium', 'Slow draining sink', 'in_progress', 2, 5, 50.00, NULL);

-- 12. GUEST FEEDBACK (Depends on reservations, guests, users)
INSERT INTO guest_feedback (reservation_id, guest_id, rating, feedback_type, category, comments, is_resolved, resolved_by, resolution_notes) VALUES
(1, 1, 4, 'compliment', 'service', 'Excellent check-in service, staff was very helpful', 1, 1, 'Thanked guest for positive feedback'),
(2, 2, 5, 'compliment', 'facilities', 'Beautiful room, perfect for our anniversary', 1, 1, 'Complimented housekeeping team'),
(3, 3, 3, 'complaint', 'cleanliness', 'Room was not cleaned properly on first day', 0, NULL, NULL);

-- 13. ACTIVITY LOGS (Depends on users)
INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent) VALUES
(1, 'login', 'User logged in successfully', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'),
(1, 'check_in', 'Checked in guest Michael Brown to room 102', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'),
(2, 'login', 'User logged in successfully', '192.168.1.101', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)'),
(2, 'housekeeping_task', 'Completed daily cleaning for room 102', '192.168.1.101', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)'),
(3, 'login', 'User logged in successfully', '192.168.1.102', 'Mozilla/5.0 (X11; Linux x86_64)'),
(3, 'reservation_created', 'Created new reservation RES20241205002', '192.168.1.102', 'Mozilla/5.0 (X11; Linux x86_64)');

-- 14. NOTIFICATIONS (Depends on users)
INSERT INTO notifications (user_id, title, message, type, is_read) VALUES
(1, 'New Reservation', 'New reservation RES20241205002 created', 'info', 0),
(2, 'Housekeeping Task', 'New cleaning task assigned for room 202', 'info', 0),
(3, 'Maintenance Alert', 'Urgent maintenance request for room 402', 'warning', 0),
(4, 'Check-in Reminder', 'Guest check-in scheduled for 2:00 PM', 'info', 1),
(5, 'Task Completed', 'Maintenance task for room 3 completed', 'success', 0);

-- 15. INVENTORY CATEGORIES (Base table - no dependencies)
-- Note: Using direct inventory table instead of categories

-- 16. INVENTORY (Base table - no dependencies)
INSERT INTO inventory (item_name, category, description, quantity, unit, reorder_level, unit_cost, supplier) VALUES
('Bath Towels', 'linens', 'White bath towels', 200, 'pieces', 50, 15.00, 'Linen Supply Co'),
('Bed Sheets', 'linens', 'King size bed sheets', 100, 'sets', 20, 45.00, 'Linen Supply Co'),
('Shampoo', 'amenities', 'Hotel brand shampoo', 500, 'bottles', 100, 2.50, 'Amenity Supply'),
('Soap', 'amenities', 'Hotel brand soap', 500, 'bars', 100, 1.50, 'Amenity Supply'),
('Cleaning Solution', 'cleaning_supplies', 'Multi-purpose cleaner', 50, 'bottles', 10, 8.00, 'Cleaning Supply Co'),
('Wrench Set', 'maintenance', 'Basic tool set', 5, 'sets', 2, 25.00, 'Tool Supply Co'),
('Mini Bar Coke', 'food_beverage', 'Coca Cola cans', 100, 'cans', 20, 3.00, 'Beverage Supply'),
('Mini Bar Water', 'food_beverage', 'Bottled water', 150, 'bottles', 30, 2.00, 'Beverage Supply');

-- 17. INVENTORY TRANSACTIONS (Depends on inventory_items, users)
INSERT INTO inventory_transactions (item_id, transaction_type, quantity, reason, performed_by) VALUES
(1, 'in', 50, 'New stock received', 3),
(2, 'in', 25, 'Replacement stock', 3),
(3, 'out', 10, 'Room 102 restocked', 2),
(4, 'out', 10, 'Room 102 restocked', 2),
(5, 'out', 2, 'Cleaning room 102', 2);

-- 18. BILLS (Depends on reservations, users)
INSERT INTO bills (bill_number, reservation_id, bill_date, due_date, subtotal, tax_amount, discount_amount, total_amount, status, notes, created_by) VALUES
('BILL20241201001', 1, '2024-12-01', '2024-12-01', 325.00, 32.50, 0.00, 357.50, 'paid', 'Room charges and room service', 1),
('BILL20241201002', 2, '2024-12-01', '2024-12-01', 1080.00, 108.00, 50.00, 1138.00, 'paid', 'Anniversary package with discount', 1),
('BILL20241202001', 3, '2024-12-02', '2024-12-02', 800.00, 86.40, 0.00, 886.40, 'pending', 'Business traveler', 4);

-- 19. BILL ITEMS (Depends on bills)
INSERT INTO bill_items (bill_id, description, quantity, unit_price, total_amount) VALUES
(1, 'Room Charges (2 nights)', 2, 150.00, 300.00),
(1, 'Room Service Breakfast', 1, 25.00, 25.00),
(2, 'Room Charges (4 nights)', 4, 250.00, 1000.00),
(2, 'Champagne Service', 1, 45.00, 45.00),
(2, 'Spa Treatment', 1, 80.00, 80.00),
(3, 'Room Charges (2 nights)', 2, 400.00, 800.00);

-- 20. PAYMENTS (Depends on bills, users)
INSERT INTO payments (payment_number, bill_id, payment_method, amount, reference_number, notes, processed_by) VALUES
('PAY20241201001', 1, 'credit_card', 357.50, 'CC123456789', 'Visa ending in 1234', 1),
('PAY20241201002', 2, 'credit_card', 1138.00, 'CC987654321', 'Mastercard ending in 5678', 1);

-- 21. DISCOUNTS (Depends on bills, users)
INSERT INTO discounts (bill_id, discount_type, discount_value, discount_amount, reason, description, applied_by) VALUES
(2, 'percentage', 5.00, 50.00, 'VIP Guest', 'VIP guest discount', 1);

-- 22. VOUCHERS (Depends on users)
INSERT INTO vouchers (voucher_code, voucher_type, voucher_value, usage_limit, valid_from, valid_until, description, status, created_by) VALUES
('WELCOME2024', 'percentage', 10.00, 100, '2024-01-01', '2024-12-31', 'Welcome discount for new guests', 'active', 3),
('SUMMER2024', 'fixed', 50.00, 50, '2024-06-01', '2024-08-31', 'Summer promotion discount', 'active', 3),
('VIP2024', 'percentage', 15.00, 25, '2024-01-01', '2024-12-31', 'VIP guest exclusive discount', 'active', 3);

-- 23. VOUCHER USAGE (Depends on vouchers, bills, users)
INSERT INTO voucher_usage (voucher_id, bill_id, used_by) VALUES
(1, 2, 1);

-- 24. LOYALTY POINTS (Depends on guests, users)
INSERT INTO loyalty_points (guest_id, action, points, reason, description, processed_by) VALUES
(1, 'earn', 300, 'Reservation completed', 'Points earned for 2-night stay', 1),
(2, 'earn', 1000, 'Reservation completed', 'Points earned for 4-night stay', 1),
(3, 'earn', 800, 'Reservation completed', 'Points earned for 2-night stay', 4);

-- 25. TRAINING SCENARIOS (Base table - no dependencies)
INSERT INTO training_scenarios (title, description, instructions, category, difficulty, estimated_time, points) VALUES
('Check-in Process', 'Handle a guest check-in with special requests', 'Follow the standard check-in procedure while accommodating guest requests', 'front_desk', 'beginner', 10, 10),
('Overbooking Situation', 'Manage an overbooking scenario', 'Handle the situation professionally and find alternative solutions', 'front_desk', 'intermediate', 15, 20),
('Guest Complaint', 'Resolve a guest complaint about room cleanliness', 'Listen actively and provide appropriate solutions', 'front_desk', 'beginner', 12, 15),
('Emergency Response', 'Handle a medical emergency in the hotel', 'Follow emergency protocols and coordinate with medical services', 'management', 'advanced', 20, 30),
('Revenue Management', 'Optimize room pricing for high demand period', 'Analyze market conditions and adjust pricing strategy', 'management', 'advanced', 25, 35);

-- 26. SCENARIO QUESTIONS (Depends on training_scenarios)
INSERT INTO scenario_questions (scenario_id, question, question_order, correct_answer) VALUES
(1, 'What is the first step in the check-in process?', 1, 'A'),
(1, 'How should you handle a guest with special requests?', 2, 'B'),
(2, 'What is the best approach to handle an overbooking?', 1, 'C'),
(3, 'How should you respond to a guest complaint?', 1, 'A'),
(4, 'What is the first action in an emergency?', 1, 'B');

-- 27. QUESTION OPTIONS (Depends on scenario_questions)
INSERT INTO question_options (question_id, option_text, option_value, option_order) VALUES
(1, 'Ask for payment', 'A', 1),
(1, 'Verify reservation', 'B', 2),
(1, 'Give room key', 'C', 3),
(1, 'Show room', 'D', 4),
(2, 'Ignore the request', 'A', 1),
(2, 'Document and accommodate', 'B', 2),
(2, 'Refuse the request', 'C', 3),
(2, 'Charge extra', 'D', 4),
(3, 'Deny responsibility', 'A', 1),
(3, 'Blame the system', 'B', 2),
(3, 'Find alternative accommodation', 'C', 3),
(3, 'Ask guest to leave', 'D', 4),
(4, 'Argue with guest', 'A', 1),
(4, 'Listen and apologize', 'B', 2),
(4, 'Ignore complaint', 'C', 3),
(4, 'Call security', 'D', 4),
(5, 'Hide the situation', 'A', 1),
(5, 'Call emergency services', 'B', 2),
(5, 'Continue business as usual', 'C', 3),
(5, 'Wait for manager', 'D', 4);

-- 28. CUSTOMER SERVICE SCENARIOS (Base table - no dependencies)
INSERT INTO customer_service_scenarios (title, description, situation, guest_request, type, difficulty, estimated_time, points) VALUES
('Noisy Neighbors', 'Handle a complaint about noisy neighbors', 'Guest reports loud music from adjacent room at 2 AM', 'Request room change or noise control', 'complaints', 'intermediate', 8, 15),
('Lost Luggage', 'Assist guest with lost luggage from airport', 'Guest arrived but luggage was lost by airline', 'Help locate and retrieve luggage', 'requests', 'beginner', 10, 12),
('Medical Emergency', 'Respond to guest medical emergency', 'Guest experiencing chest pain in lobby', 'Coordinate emergency medical response', 'emergencies', 'advanced', 15, 25),
('Room Upgrade Request', 'Handle request for room upgrade', 'Guest unhappy with current room and wants upgrade', 'Evaluate upgrade availability and options', 'requests', 'intermediate', 12, 18),
('Billing Dispute', 'Resolve billing discrepancy', 'Guest disputes charges on final bill', 'Review charges and resolve dispute', 'complaints', 'intermediate', 15, 20);

-- 29. PROBLEM SCENARIOS (Base table - no dependencies)
INSERT INTO problem_scenarios (title, description, resources, severity, difficulty, time_limit, points) VALUES
('Power Outage', 'Hotel experiencing partial power outage', 'Emergency generator, backup lighting, guest list', 'high', 'intermediate', 20, 25),
('Water Leak', 'Major water leak in guest room', 'Maintenance team, water damage equipment, room availability', 'critical', 'advanced', 30, 35),
('Staff Shortage', 'Multiple staff members called in sick', 'Available staff list, task priorities, manager contact', 'medium', 'intermediate', 25, 20),
('System Failure', 'PMS system down during peak check-in', 'Manual procedures, backup systems, guest patience', 'high', 'advanced', 40, 30),
('Weather Emergency', 'Severe weather affecting hotel operations', 'Emergency protocols, guest safety, communication systems', 'critical', 'advanced', 45, 40);

-- 30. TRAINING ATTEMPTS (Depends on users, training scenarios)
INSERT INTO training_attempts (user_id, scenario_id, scenario_type, answers, score, duration_minutes, status) VALUES
(1, 1, 'scenario', '{"1":"A","2":"B"}', 100.00, 8, 'completed'),
(1, 2, 'scenario', '{"1":"C"}', 100.00, 12, 'completed'),
(2, 3, 'scenario', '{"1":"A"}', 100.00, 10, 'completed'),
(4, 1, 'scenario', '{"1":"A","2":"B"}', 100.00, 9, 'completed'),
(4, 4, 'scenario', '{"1":"B"}', 100.00, 18, 'completed');

-- 31. TRAINING CERTIFICATES (Depends on users)
INSERT INTO training_certificates (user_id, name, type, status, earned_at) VALUES
(1, 'Front Desk Operations Certificate', 'scenario', 'earned', '2024-12-01 10:00:00'),
(2, 'Housekeeping Excellence Certificate', 'scenario', 'earned', '2024-12-01 14:30:00'),
(4, 'Customer Service Certificate', 'scenario', 'earned', '2024-12-02 09:15:00');

-- Display seeding completion message
SELECT 'Database seeding completed successfully!' as message;
SELECT COUNT(*) as total_users FROM users;
SELECT COUNT(*) as total_rooms FROM rooms;
SELECT COUNT(*) as total_guests FROM guests;
SELECT COUNT(*) as total_reservations FROM reservations;
SELECT COUNT(*) as total_bills FROM bills;
SELECT COUNT(*) as total_training_scenarios FROM training_scenarios;
