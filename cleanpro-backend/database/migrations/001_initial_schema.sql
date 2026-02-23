-- CleanPro SaaS - Schéma de base de données initial

-- Table des utilisateurs
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    role ENUM('admin', 'agent', 'client') NOT NULL DEFAULT 'client',
    is_active BOOLEAN DEFAULT TRUE,
    last_login DATETIME,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_email (email),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des clients
CREATE TABLE clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    company_name VARCHAR(255) NOT NULL,
    contact_name VARCHAR(255),
    contact_email VARCHAR(255),
    contact_phone VARCHAR(20),
    address TEXT,
    postal_code VARCHAR(10),
    city VARCHAR(100),
    siret VARCHAR(14),
    vat_number VARCHAR(20),
    billing_type ENUM('one_time', 'monthly', 'annual') DEFAULT 'one_time',
    monthly_quota INT DEFAULT 0,
    quota_used INT DEFAULT 0,
    monthly_amount DECIMAL(10, 2) DEFAULT 0.00,
    notes TEXT,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_billing_type (billing_type),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des services
CREATE TABLE services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    category ENUM('cleaning', 'gardening', 'maintenance') NOT NULL,
    base_price DECIMAL(10, 2) NOT NULL,
    unit VARCHAR(50) DEFAULT 'heure',
    estimated_duration INT DEFAULT 60, -- en minutes
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_category (category),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des interventions
CREATE TABLE interventions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reference VARCHAR(50) NOT NULL UNIQUE,
    client_id INT NOT NULL,
    agent_id INT NOT NULL,
    scheduled_date DATE NOT NULL,
    scheduled_time TIME NOT NULL,
    estimated_end_time TIME,
    actual_start_time DATETIME,
    actual_end_time DATETIME,
    type ENUM('one_time', 'recurring') DEFAULT 'one_time',
    status ENUM('scheduled', 'in_progress', 'completed', 'validated', 'cancelled') DEFAULT 'scheduled',
    priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    total_amount DECIMAL(10, 2) DEFAULT 0.00,
    notes TEXT,
    agent_signature TEXT,
    client_signature TEXT,
    client_feedback TEXT,
    client_rating INT,
    validated_at DATETIME,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (agent_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_client (client_id),
    INDEX idx_agent (agent_id),
    INDEX idx_date (scheduled_date),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table de liaison interventions-services
CREATE TABLE intervention_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    intervention_id INT NOT NULL,
    service_id INT NOT NULL,
    quantity DECIMAL(10, 2) DEFAULT 1.00,
    unit_price DECIMAL(10, 2) NOT NULL,
    total_price DECIMAL(10, 2) NOT NULL,
    notes TEXT,
    FOREIGN KEY (intervention_id) REFERENCES interventions(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE RESTRICT,
    INDEX idx_intervention (intervention_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des factures
CREATE TABLE invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(50) NOT NULL UNIQUE,
    client_id INT NOT NULL,
    intervention_id INT,
    type ENUM('one_time', 'recurring') DEFAULT 'one_time',
    status ENUM('draft', 'sent', 'paid', 'overdue', 'cancelled') DEFAULT 'draft',
    issue_date DATE NOT NULL,
    due_date DATE NOT NULL,
    paid_at DATETIME,
    payment_method VARCHAR(50),
    transaction_id VARCHAR(255),
    subtotal DECIMAL(10, 2) DEFAULT 0.00,
    discount_amount DECIMAL(10, 2) DEFAULT 0.00,
    discount_reason VARCHAR(255),
    tax_amount DECIMAL(10, 2) DEFAULT 0.00,
    total_amount DECIMAL(10, 2) DEFAULT 0.00,
    promo_code_id INT,
    notes TEXT,
    sent_at DATETIME,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE RESTRICT,
    FOREIGN KEY (intervention_id) REFERENCES interventions(id) ON DELETE SET NULL,
    INDEX idx_client (client_id),
    INDEX idx_status (status),
    INDEX idx_issue_date (issue_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des lignes de facture
CREATE TABLE invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    service_id INT,
    description TEXT NOT NULL,
    quantity DECIMAL(10, 2) DEFAULT 1.00,
    unit_price DECIMAL(10, 2) NOT NULL,
    total_price DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL,
    INDEX idx_invoice (invoice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table de liaison factures-interventions (pour factures groupées)
CREATE TABLE invoice_interventions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    intervention_id INT NOT NULL,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (intervention_id) REFERENCES interventions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_invoice_intervention (invoice_id, intervention_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des codes promo
CREATE TABLE promo_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    discount_type ENUM('percentage', 'fixed') NOT NULL,
    discount_value DECIMAL(10, 2) NOT NULL,
    max_uses INT DEFAULT NULL,
    used_count INT DEFAULT 0,
    valid_from DATE,
    valid_until DATE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_code (code),
    INDEX idx_validity (valid_from, valid_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des demandes de devis
CREATE TABLE quote_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    service_type VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    preferred_date DATE,
    estimated_budget DECIMAL(10, 2),
    status ENUM('pending', 'processed', 'accepted', 'rejected') DEFAULT 'pending',
    admin_notes TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Données de test
INSERT INTO users (email, password_hash, first_name, last_name, phone, role, is_active, created_at, updated_at) VALUES
('admin@cleanpro.fr', '$argon2id$v=19$m=65536,t=4,p=3$...', 'Admin', 'System', '01 23 45 67 89', 'admin', TRUE, NOW(), NOW()),
('agent1@cleanpro.fr', '$argon2id$v=19$m=65536,t=4,p=3$...', 'Jean', 'Dupont', '06 12 34 56 78', 'agent', TRUE, NOW(), NOW()),
('agent2@cleanpro.fr', '$argon2id$v=19$m=65536,t=4,p=3$...', 'Marie', 'Martin', '06 23 45 67 89', 'agent', TRUE, NOW(), NOW());

INSERT INTO services (name, description, category, base_price, unit, estimated_duration, status, created_at, updated_at) VALUES
('Nettoyage standard', 'Nettoyage complet des locaux', 'cleaning', 35.00, 'heure', 120, 'active', NOW(), NOW()),
('Nettoyage vitres', 'Nettoyage intérieur/extérieur des vitres', 'cleaning', 45.00, 'heure', 60, 'active', NOW(), NOW()),
('Tonte de pelouse', 'Tonte et bordure de pelouse', 'gardening', 40.00, 'heure', 90, 'active', NOW(), NOW()),
('Taille de haies', 'Taille et forme des haies', 'gardening', 50.00, 'heure', 120, 'active', NOW(), NOW()),
('Maintenance HVAC', 'Entretien climatisation et chauffage', 'maintenance', 75.00, 'heure', 60, 'active', NOW(), NOW()),
('Réparation électrique', 'Dépannage et réparations électriques', 'maintenance', 65.00, 'heure', 90, 'active', NOW(), NOW());
