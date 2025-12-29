CREATE DATABASE IF NOT EXISTS lume_db;
USE lume_db;

-- Users Table
DROP TABLE IF EXISTS users;
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Work Profiles Table
DROP TABLE IF EXISTS work_profiles;
CREATE TABLE work_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    salary DECIMAL(10, 2),
    payment_type ENUM('monthly', 'hourly') DEFAULT 'monthly',
    work_days JSON,
    work_start TIME,
    work_end TIME,
    has_interval BOOLEAN DEFAULT TRUE,
    interval_start TIME,
    interval_end TIME,
    initial_balance DECIMAL(10, 2) DEFAULT 0.00,
    last_configured_month VARCHAR(7),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Expenses Table
-- Transactions Table (Replaces expenses)
DROP TABLE IF EXISTS transactions;
CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('income', 'expense') NOT NULL,
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    category VARCHAR(50),
    transaction_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
