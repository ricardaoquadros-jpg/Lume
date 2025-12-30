-- =============================================
-- LUME - TABELAS DE INVESTIMENTOS v2
-- Com suporte a múltiplos aportes
-- Execute no phpMyAdmin
-- =============================================

-- Tabela principal de investimentos (carteira)
CREATE TABLE IF NOT EXISTS investments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    type VARCHAR(50) DEFAULT 'Outro',
    current_value DECIMAL(12,2) DEFAULT 0,
    last_update DATE,
    notes TEXT,
    active TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Transações do investimento (aportes, retiradas, atualizações de valor)
CREATE TABLE IF NOT EXISTS investment_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    investment_id INT NOT NULL,
    type ENUM('deposit', 'withdrawal', 'update') NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    balance_after DECIMAL(12,2),
    transaction_date DATE NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (investment_id) REFERENCES investments(id) ON DELETE CASCADE
);

-- Índice para performance
CREATE INDEX idx_inv_trans_date ON investment_transactions(investment_id, transaction_date);
