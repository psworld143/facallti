-- Additional Services and Service Charges Data Insertion Script
-- This script adds comprehensive sample data for additional services and service charges

USE hotel_pms_clean;

-- Clear existing data (optional - uncomment if you want to start fresh)
-- DELETE FROM service_charges;
-- DELETE FROM additional_services;

-- Insert comprehensive additional services data
INSERT INTO additional_services (name, description, price, category, is_active) VALUES
-- Food & Beverage Services
('Room Service Breakfast', 'Continental breakfast delivered to room', 25.00, 'food_beverage', 1),
('Room Service Lunch', 'Hot lunch menu delivered to room', 35.00, 'food_beverage', 1),
('Room Service Dinner', 'Gourmet dinner menu delivered to room', 45.00, 'food_beverage', 1),
('Late Night Snacks', 'Assorted snacks and beverages', 20.00, 'food_beverage', 1),
('Champagne Service', 'Bottle of champagne with glasses', 75.00, 'food_beverage', 1),
('Coffee & Tea Service', 'Premium coffee and tea selection', 15.00, 'food_beverage', 1),
('Wine Service', 'Bottle of house wine with glasses', 45.00, 'food_beverage', 1),
('Birthday Cake', 'Custom birthday cake with candles', 30.00, 'food_beverage', 1),
('Anniversary Package', 'Chocolate covered strawberries and champagne', 60.00, 'food_beverage', 1),

-- Laundry Services
('Same Day Laundry', 'Express laundry service (same day)', 25.00, 'laundry', 1),
('Standard Laundry', 'Regular laundry service (next day)', 15.00, 'laundry', 1),
('Dry Cleaning', 'Professional dry cleaning service', 20.00, 'laundry', 1),
('Pressing Service', 'Quick pressing of garments', 12.00, 'laundry', 1),
('Shoe Shine', 'Professional shoe shining service', 8.00, 'laundry', 1),
('Ironing Service', 'Complete ironing service', 10.00, 'laundry', 1),

-- Spa Services
('Swedish Massage', 'Relaxing Swedish massage (60 min)', 80.00, 'spa', 1),
('Deep Tissue Massage', 'Therapeutic deep tissue massage (60 min)', 90.00, 'spa', 1),
('Hot Stone Massage', 'Luxurious hot stone massage (75 min)', 100.00, 'spa', 1),
('Facial Treatment', 'Rejuvenating facial treatment (45 min)', 70.00, 'spa', 1),
('Manicure & Pedicure', 'Complete nail care service', 50.00, 'spa', 1),
('Couples Massage', 'Romantic couples massage (90 min)', 150.00, 'spa', 1),
('Aromatherapy Session', 'Relaxing aromatherapy treatment (30 min)', 45.00, 'spa', 1),

-- Transportation Services
('Airport Transfer', 'Round trip airport transportation', 50.00, 'transportation', 1),
('City Tour', 'Guided city tour with professional guide', 75.00, 'transportation', 1),
('Luxury Car Rental', 'Premium car rental service', 120.00, 'transportation', 1),
('Shuttle Service', 'Hotel shuttle to local attractions', 25.00, 'transportation', 1),
('Limousine Service', 'Luxury limousine transportation', 200.00, 'transportation', 1),
('Valet Parking', 'Professional valet parking service', 15.00, 'transportation', 1),

-- Other Services
('Concierge Service', 'Personal concierge assistance', 30.00, 'other', 1),
('Business Center Access', 'Access to business center facilities', 20.00, 'other', 1),
('Gym Access', 'Access to hotel fitness center', 15.00, 'other', 1),
('Pool Towel Service', 'Fresh pool towels and service', 5.00, 'other', 1),
('Pet Sitting', 'Professional pet sitting service', 40.00, 'other', 1),
('Photography Service', 'Professional photography service', 100.00, 'other', 1),
('Translation Service', 'Professional translation assistance', 35.00, 'other', 1),
('Medical Assistance', 'On-call medical assistance', 50.00, 'other', 1);

-- Insert sample service charges (linking to existing reservations and services)
INSERT INTO service_charges (reservation_id, service_id, quantity, unit_price, total_price, notes, charged_by) VALUES
-- Reservation 1 (Michael Brown - Room 102)
(1, 1, 1, 25.00, 25.00, 'Breakfast delivered at 8:30 AM', 1),
(1, 11, 2, 15.00, 30.00, 'Laundry service for 2 shirts', 1),
(1, 25, 1, 50.00, 50.00, 'Airport pickup service', 1),

-- Reservation 2 (Jennifer Davis - Room 203)
(2, 5, 1, 75.00, 75.00, 'Champagne for anniversary celebration', 1),
(2, 8, 1, 30.00, 30.00, 'Birthday cake for celebration', 1),
(2, 17, 1, 80.00, 80.00, 'Swedish massage for relaxation', 1),
(2, 25, 1, 50.00, 50.00, 'Airport transfer service', 1),

-- Reservation 3 (Robert Wilson - Room 302)
(3, 2, 1, 35.00, 35.00, 'Lunch delivered to room', 4),
(3, 12, 1, 20.00, 20.00, 'Dry cleaning for business suit', 4),
(3, 28, 1, 30.00, 30.00, 'Concierge service for business needs', 4),

-- Reservation 4 (Lisa Anderson - Room 202)
(4, 9, 1, 60.00, 60.00, 'Anniversary package with strawberries', 1),
(4, 18, 1, 90.00, 90.00, 'Deep tissue massage', 1),
(4, 25, 1, 50.00, 50.00, 'Airport transfer service', 1),

-- Reservation 5 (James Taylor - Room 101)
(5, 1, 1, 25.00, 25.00, 'Early breakfast for hiking trip', 1),
(5, 11, 1, 15.00, 15.00, 'Laundry service for hiking clothes', 1),
(5, 26, 1, 25.00, 25.00, 'Shuttle to hiking trail', 1),

-- Reservation 6 (Amanda Martinez - Room 301)
(6, 3, 1, 45.00, 45.00, 'Family dinner in room', 4),
(6, 6, 1, 15.00, 15.00, 'Coffee service for parents', 4),
(6, 11, 3, 15.00, 45.00, 'Laundry service for family', 4),

-- Reservation 7 (Christopher Garcia - Room 401)
(7, 5, 1, 75.00, 75.00, 'Champagne for business celebration', 1),
(7, 7, 1, 45.00, 45.00, 'Wine service for dinner', 1),
(7, 29, 1, 20.00, 20.00, 'Business center access', 1),
(7, 25, 1, 50.00, 50.00, 'Airport transfer service', 1),

-- Reservation 8 (Jessica Rodriguez - Room 103)
(8, 1, 1, 25.00, 25.00, 'Breakfast service', 4),
(8, 30, 1, 15.00, 15.00, 'Gym access for fitness routine', 4),
(8, 32, 1, 40.00, 40.00, 'Pet sitting for service dog', 4),

-- Reservation 9 (Daniel Lee - Room 101)
(9, 2, 1, 35.00, 35.00, 'Lunch during tech conference', 1),
(9, 29, 1, 20.00, 20.00, 'Business center access for work', 1),
(9, 6, 2, 15.00, 30.00, 'Coffee service for late night work', 1),

-- Reservation 10 (Nicole White - Room 401)
(10, 5, 1, 75.00, 75.00, 'Champagne for wine tasting weekend', 1),
(10, 7, 2, 45.00, 90.00, 'Wine service for tasting', 1),
(10, 9, 1, 60.00, 60.00, 'Anniversary package', 1),
(10, 18, 1, 90.00, 90.00, 'Deep tissue massage', 1),
(10, 25, 1, 50.00, 50.00, 'Airport transfer service', 1);

-- Display completion message and statistics
SELECT 'Additional Services and Service Charges data inserted successfully!' as message;
SELECT COUNT(*) as total_additional_services FROM additional_services;
SELECT COUNT(*) as total_service_charges FROM service_charges;
SELECT category, COUNT(*) as service_count FROM additional_services GROUP BY category;
SELECT 
    additional_services.name as service_name,
    additional_services.category,
    additional_services.price,
    COUNT(service_charges.id) as times_ordered,
    SUM(service_charges.total_price) as total_revenue
FROM additional_services
LEFT JOIN service_charges ON additional_services.id = service_charges.service_id
GROUP BY additional_services.id, additional_services.name, additional_services.category, additional_services.price
ORDER BY total_revenue DESC;
