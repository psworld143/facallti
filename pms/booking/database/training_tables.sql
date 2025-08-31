-- Training Database Tables
-- This script creates the necessary tables for the training system

-- Training Scenarios Table
CREATE TABLE IF NOT EXISTS training_scenarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    scenario_id VARCHAR(50) UNIQUE NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    category VARCHAR(50) NOT NULL,
    difficulty ENUM('beginner', 'intermediate', 'advanced') NOT NULL,
    estimated_time INT NOT NULL DEFAULT 15,
    points INT NOT NULL DEFAULT 100,
    status ENUM('active', 'inactive', 'draft') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Customer Service Scenarios Table
CREATE TABLE IF NOT EXISTS customer_service_scenarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    scenario_id VARCHAR(50) UNIQUE NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    type ENUM('complaints', 'requests', 'emergencies') NOT NULL,
    difficulty ENUM('beginner', 'intermediate', 'advanced') NOT NULL,
    estimated_time INT NOT NULL DEFAULT 20,
    points INT NOT NULL DEFAULT 150,
    status ENUM('active', 'inactive', 'draft') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Problem Scenarios Table
CREATE TABLE IF NOT EXISTS problem_scenarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    scenario_id VARCHAR(50) UNIQUE NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    severity ENUM('low', 'medium', 'high', 'critical') NOT NULL,
    difficulty ENUM('beginner', 'intermediate', 'advanced') NOT NULL,
    time_limit INT NOT NULL DEFAULT 15,
    points INT NOT NULL DEFAULT 200,
    status ENUM('active', 'inactive', 'draft') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Training Attempts Table
CREATE TABLE IF NOT EXISTS training_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    scenario_id VARCHAR(50) NOT NULL,
    scenario_type ENUM('training', 'customer_service', 'problem') NOT NULL,
    score DECIMAL(5,2) NOT NULL,
    answers JSON,
    duration_minutes INT DEFAULT 0,
    status ENUM('in_progress', 'completed', 'abandoned') DEFAULT 'in_progress',
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Training Certificates Table
CREATE TABLE IF NOT EXISTS training_certificates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    certificate_name VARCHAR(255) NOT NULL,
    certificate_type VARCHAR(100) NOT NULL,
    score DECIMAL(5,2) NOT NULL,
    status ENUM('earned', 'expired', 'revoked') DEFAULT 'earned',
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert sample training scenarios
INSERT INTO training_scenarios (scenario_id, title, description, category, difficulty, estimated_time, points) VALUES
('front_desk_basic', 'Front Desk Check-in Process', 'Learn essential check-in and check-out procedures with real scenarios.', 'front_desk', 'beginner', 15, 100),
('customer_service', 'Customer Service Excellence', 'Handle various customer service situations including complaints and special requests.', 'customer_service', 'intermediate', 25, 200),
('problem_solving', 'Problem Solving & Crisis Management', 'Handle various hotel problems and crisis situations that require quick thinking.', 'problem_solving', 'advanced', 30, 300)
ON DUPLICATE KEY UPDATE 
    title = VALUES(title),
    description = VALUES(description),
    category = VALUES(category),
    difficulty = VALUES(difficulty),
    estimated_time = VALUES(estimated_time),
    points = VALUES(points);

-- Insert sample customer service scenarios
INSERT INTO customer_service_scenarios (scenario_id, title, description, type, difficulty, estimated_time, points) VALUES
('customer_service', 'Customer Service Excellence', 'Handle various customer service situations including complaints and special requests.', 'complaints', 'intermediate', 25, 200),
('complaint_handling', 'Handling Guest Complaints', 'Practice responding to common guest complaints professionally.', 'complaints', 'beginner', 20, 150),
('special_requests', 'Special Guest Requests', 'Handle unusual guest requests with professionalism and creativity.', 'requests', 'advanced', 30, 250)
ON DUPLICATE KEY UPDATE 
    title = VALUES(title),
    description = VALUES(description),
    type = VALUES(type),
    difficulty = VALUES(difficulty),
    estimated_time = VALUES(estimated_time),
    points = VALUES(points);

-- Insert sample problem scenarios
INSERT INTO problem_scenarios (scenario_id, title, description, severity, difficulty, time_limit, points) VALUES
('problem_solving', 'Problem Solving & Crisis Management', 'Handle various hotel problems and crisis situations that require quick thinking.', 'high', 'advanced', 15, 300)
ON DUPLICATE KEY UPDATE 
    title = VALUES(title),
    description = VALUES(description),
    severity = VALUES(severity),
    difficulty = VALUES(difficulty),
    time_limit = VALUES(time_limit),
    points = VALUES(points);

-- Create indexes for better performance
CREATE INDEX idx_training_scenarios_category ON training_scenarios(category);
CREATE INDEX idx_training_scenarios_difficulty ON training_scenarios(difficulty);
CREATE INDEX idx_training_scenarios_status ON training_scenarios(status);

CREATE INDEX idx_customer_service_scenarios_type ON customer_service_scenarios(type);
CREATE INDEX idx_customer_service_scenarios_difficulty ON customer_service_scenarios(difficulty);
CREATE INDEX idx_customer_service_scenarios_status ON customer_service_scenarios(status);

CREATE INDEX idx_problem_scenarios_severity ON problem_scenarios(severity);
CREATE INDEX idx_problem_scenarios_difficulty ON problem_scenarios(difficulty);
CREATE INDEX idx_problem_scenarios_status ON problem_scenarios(status);

CREATE INDEX idx_training_attempts_user_id ON training_attempts(user_id);
CREATE INDEX idx_training_attempts_scenario_id ON training_attempts(scenario_id);
CREATE INDEX idx_training_attempts_status ON training_attempts(status);

CREATE INDEX idx_training_certificates_user_id ON training_certificates(user_id);
CREATE INDEX idx_training_certificates_status ON training_certificates(status);
