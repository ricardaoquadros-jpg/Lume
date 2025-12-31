-- Database Definition
CREATE DATABASE IF NOT EXISTS lume_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE lume_db;

-- Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Work Profiles Table (Settings & AI Profile)
CREATE TABLE IF NOT EXISTS work_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    salary DECIMAL(10, 2) DEFAULT 0.00,
    payment_type VARCHAR(50) DEFAULT 'monthly', -- 'monthly' or 'hourly'
    work_days JSON DEFAULT NULL, -- Stores array of days [1, 2, ..., 31]
    work_start VARCHAR(5) DEFAULT '09:00',
    work_end VARCHAR(5) DEFAULT '18:00',
    has_interval TINYINT(1) DEFAULT 1,
    interval_start VARCHAR(5) DEFAULT '12:00',
    interval_end VARCHAR(5) DEFAULT '13:00',
    initial_balance DECIMAL(10, 2) DEFAULT 0.00,
    last_configured_month VARCHAR(7) DEFAULT NULL, -- Format 'YYYY-MM'
    ai_profile_data TEXT DEFAULT NULL, -- Stores AI analyzed user persona
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Transactions Table (Income & Expenses)
CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(20) NOT NULL, -- 'income' or 'expense'
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    category VARCHAR(100) DEFAULT 'Outros',
    transaction_date DATE NOT NULL,
    transcription TEXT DEFAULT NULL, -- Stores voice transcription if added via voice
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Investments Table
CREATE TABLE IF NOT EXISTS investments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    type VARCHAR(100) DEFAULT 'Outro',
    current_value DECIMAL(15, 2) DEFAULT 0.00,
    active TINYINT(1) DEFAULT 1, -- 1 = Active, 0 = Deleted/Archived
    last_update DATE DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Investment Transactions Table (History of deposits/withdrawals/updates)
CREATE TABLE IF NOT EXISTS investment_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    investment_id INT NOT NULL,
    type VARCHAR(50) NOT NULL, -- 'deposit', 'withdrawal', 'update'
    amount DECIMAL(15, 2) NOT NULL,
    balance_after DECIMAL(15, 2) DEFAULT 0.00,
    transaction_date DATE NOT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (investment_id) REFERENCES investments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Recurring Transactions Table
CREATE TABLE IF NOT EXISTS recurring_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(20) NOT NULL, -- 'income' or 'expense'
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    category VARCHAR(100) DEFAULT 'Outros',
    frequency VARCHAR(20) DEFAULT 'monthly', -- 'monthly', 'weekly', 'yearly'
    day_of_month INT DEFAULT 1, -- -1 means last day of month
    active TINYINT(1) DEFAULT 1,
    last_executed DATE DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
