CREATE DATABASE IF NOT EXISTS reuseit CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE reuseit;

-- ==================== TABELLA UTENTI ====================
CREATE TABLE users (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  phone_number VARCHAR(20),
  profile_picture_url VARCHAR(500),
  bio TEXT,
  address_street VARCHAR(255),
  address_city VARCHAR(100),
  address_province VARCHAR(100),
  address_postal_code VARCHAR(10),
  address_country VARCHAR(100),
  latitude DECIMAL(10, 8),
  longitude DECIMAL(11, 8),
  rating_average DECIMAL(3, 2) DEFAULT 0,
  rating_count INT DEFAULT 0,
  is_verified BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL,
  
  INDEX idx_email (email),
  INDEX idx_created_at (created_at),
  INDEX idx_coordinates (latitude, longitude),
  INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== TABELLA CATEGORIE ====================
CREATE TABLE categories (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL UNIQUE,
  description TEXT,
  icon_url VARCHAR(500),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== TABELLA ANNUNCI ====================
CREATE TABLE listings (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  seller_id BIGINT NOT NULL,
  category_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT NOT NULL,
  price DECIMAL(10, 2) NOT NULL,
  brand VARCHAR(100),
  model VARCHAR(100),
  year INT,
  condition VARCHAR(50),
  accessories JSON,
  latitude DECIMAL(10, 8) NOT NULL,
  longitude DECIMAL(11, 8) NOT NULL,
  location_address VARCHAR(500),
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  view_count INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL,
  
  FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (category_id) REFERENCES categories(id),
  
  INDEX idx_seller_id (seller_id),
  INDEX idx_status (status),
  INDEX idx_created_at (created_at),
  INDEX idx_category_id (category_id),
  INDEX idx_coordinates (latitude, longitude),
  INDEX idx_seller_status (seller_id, status),
  INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== TABELLA FOTO ANNUNCI ====================
CREATE TABLE listing_photos (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  listing_id BIGINT NOT NULL,
  photo_url VARCHAR(500) NOT NULL,
  display_order INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL,
  
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  
  INDEX idx_listing_id (listing_id),
  INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== TABELLA PRENOTAZIONI ====================
CREATE TABLE bookings (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  listing_id BIGINT NOT NULL UNIQUE,
  buyer_id BIGINT NOT NULL,
  seller_id BIGINT NOT NULL,
  booking_status VARCHAR(20) NOT NULL DEFAULT 'pending',
  booking_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  scheduled_pickup_date TIMESTAMP NULL,
  completed_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL,
  
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
  
  INDEX idx_buyer_id (buyer_id),
  INDEX idx_seller_id (seller_id),
  INDEX idx_booking_status (booking_status),
  INDEX idx_booking_date (booking_date),
  INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== TABELLA CONVERSAZIONI ====================
CREATE TABLE conversations (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  listing_id BIGINT NOT NULL,
  buyer_id BIGINT NOT NULL,
  seller_id BIGINT NOT NULL,
  last_message_at TIMESTAMP NULL,
  unread_by_seller BOOLEAN DEFAULT TRUE,
  unread_by_buyer BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL,
  
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
  
  UNIQUE KEY unique_conversation (listing_id, buyer_id, seller_id),
  INDEX idx_buyer_id (buyer_id),
  INDEX idx_seller_id (seller_id),
  INDEX idx_last_message_at (last_message_at),
  INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== TABELLA MESSAGGI ====================
CREATE TABLE messages (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  conversation_id BIGINT NOT NULL,
  sender_id BIGINT NOT NULL,
  content TEXT NOT NULL,
  is_read BOOLEAN DEFAULT FALSE,
  read_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL,
  
  FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
  FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
  
  INDEX idx_conversation_id (conversation_id),
  INDEX idx_sender_id (sender_id),
  INDEX idx_created_at (created_at),
  INDEX idx_conversation_read (conversation_id, is_read),
  INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== TABELLA VALUTAZIONI ====================
CREATE TABLE reviews (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  listing_id BIGINT NOT NULL,
  reviewer_id BIGINT NOT NULL,
  reviewed_user_id BIGINT NOT NULL,
  rating INT NOT NULL,
  comment TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL,
  
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (reviewed_user_id) REFERENCES users(id) ON DELETE CASCADE,
  
  UNIQUE KEY unique_review (listing_id, reviewer_id),
  INDEX idx_reviewed_user (reviewed_user_id),
  INDEX idx_reviewer_id (reviewer_id),
  INDEX idx_created_at (created_at),
  INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== TABELLA PREFERITI ====================
CREATE TABLE favorites (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT NOT NULL,
  listing_id BIGINT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL,
  
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  
  UNIQUE KEY unique_favorite (user_id, listing_id),
  INDEX idx_user_id (user_id),
  INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== TABELLA SEGNALAZIONI ====================
CREATE TABLE reports (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  reporter_id BIGINT NOT NULL,
  listing_id BIGINT,
  reported_user_id BIGINT,
  reason VARCHAR(100) NOT NULL,
  description TEXT,
  status VARCHAR(20) DEFAULT 'open',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL,
  
  FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE SET NULL,
  FOREIGN KEY (reported_user_id) REFERENCES users(id) ON DELETE SET NULL,
  
  INDEX idx_status (status),
  INDEX idx_created_at (created_at),
  INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== TABELLA SESSIONI ====================
CREATE TABLE sessions (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  session_id VARCHAR(255) NOT NULL UNIQUE,
  user_id BIGINT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expires_at TIMESTAMP NOT NULL,
  data LONGTEXT,
  
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  
  INDEX idx_session_id (session_id),
  INDEX idx_user_id (user_id),
  INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- DATI DI TEST
-- ============================================================

-- Inserimento categorie
INSERT INTO categories (name, description) VALUES
('Smartphone', 'Telefoni cellulari e smartphone'),
('Laptop', 'Computer portatili e notebook'),
('Tablet', 'Tablet e e-reader'),
('Fotocamere', 'Fotocamere digitali e mirrorless'),
('Console', 'Console da gioco'),
('Auricolari', 'Auricolari e cuffie'),
('Smartwatch', 'Orologi intelligenti');

-- Inserimento utenti
INSERT INTO users (email, password_hash, first_name, last_name, phone_number, bio, address_street, address_city, address_province, address_postal_code, address_country, latitude, longitude, rating_average, rating_count, is_verified) VALUES
('luca.rossi@email.com', '$2y$10$hash1', 'Luca', 'Rossi', '+39 338 1234567', 'Venditore affidabile con esperienza', 'Via Roma 10', 'Milano', 'MI', '20100', 'Italia', 45.4642, 9.1900, 4.8, 12, TRUE),
('maria.bianchi@email.com', '$2y$10$hash2', 'Maria', 'Bianchi', '+39 349 8765432', 'Appassionata di tecnologia', 'Via Verdi 20', 'Roma', 'RM', '00100', 'Italia', 41.9028, 12.4964, 4.5, 8, TRUE),
('giuseppe.ferrari@email.com', '$2y$10$hash3', 'Giuseppe', 'Ferrari', '+39 331 5555555', 'Collezionista', 'Via Dante 30', 'Torino', 'TO', '10100', 'Italia', 45.0703, 7.6869, 4.9, 15, TRUE),
('anna.colombo@email.com', '$2y$10$hash4', 'Anna', 'Colombo', '+39 320 9999999', 'Buyer attivo', 'Via Cavour 40', 'Firenze', 'FI', '50100', 'Italia', 43.7696, 11.2558, 4.6, 10, FALSE),
('marco.gallo@email.com', '$2y$10$hash5', 'Marco', 'Gallo', '+39 333 7777777', 'Nuovo membro', 'Via Garibaldi 50', 'Bologna', 'BO', '40100', 'Italia', 44.4969, 11.3438, 0.0, 0, FALSE);

-- Inserimento annunci
INSERT INTO listings (seller_id, category_id, title, description, price, brand, model, year, condition, accessories, latitude, longitude, location_address, status, view_count) VALUES
(1, 1, 'iPhone 12 Pro in perfette condizioni', 'iPhone 12 Pro 128GB Blu, scatola originale, caricabatterie incluso. Nessun graffio.', 650.00, 'Apple', 'iPhone 12 Pro', 2021, 'Ottimo', '[\"caricabatterie\", \"scatola\"]', 45.4642, 9.1900, 'Milano, MI', 'active', 145),
(1, 2, 'MacBook Air M1 13 pollici', 'MacBook Air M1, 256GB SSD, 8GB RAM, 2021, pochissimo utilizzato.', 899.00, 'Apple', 'MacBook Air M1', 2021, 'Ottimo', '[\"caricabatterie\"]', 45.4642, 9.1900, 'Milano, MI', 'active', 89),
(2, 1, 'Samsung Galaxy S21 Ultra Nero', 'Samsung Galaxy S21 Ultra 256GB, buone condizioni, qualche piccolo graffio sullo schermo.', 450.00, 'Samsung', 'Galaxy S21 Ultra', 2021, 'Buono', '[]', 41.9028, 12.4964, 'Roma, RM', 'active', 67),
(2, 3, 'iPad Air 4 Azzurro', 'iPad Air 4 64GB con Apple Pencil, condizioni eccellenti, usato poco.', 380.00, 'Apple', 'iPad Air 4', 2020, 'Ottimo', '[\"Apple Pencil\", \"caricabatterie\"]', 41.9028, 12.4964, 'Roma, RM', 'booked', 120),
(3, 4, 'Canon EOS M50 Mark II', 'Fotocamera mirrorless con lente 15-45mm, perfette condizioni, completa di batteria e caricabatterie.', 550.00, 'Canon', 'EOS M50 Mark II', 2020, 'Ottimo', '[\"batteria extra\", \"scheda 64GB\"]', 45.0703, 7.6869, 'Torino, TO', 'active', 101),
(3, 6, 'AirPods Pro - Originali', 'AirPods Pro originali Apple, custodia di ricarica wireless, come nuove.', 180.00, 'Apple', 'AirPods Pro', 2021, 'Ottimo', '[\"custodia ricarica\", \"caricabatterie\"]', 45.0703, 7.6869, 'Torino, TO', 'active', 234),
(1, 5, 'Nintendo Switch OLED Bianca', 'Nintendo Switch OLED, scatola originale, dock, Joy-Con, pochissimo usata.', 320.00, 'Nintendo', 'Switch OLED', 2021, 'Ottimo', '[\"dock\", \"Joy-Con\", \"cavetti\"]', 45.4642, 9.1900, 'Milano, MI', 'completed', 312);

-- Inserimento foto annunci
INSERT INTO listing_photos (listing_id, photo_url, display_order) VALUES
(1, 'https://example.com/iphone12pro_1.jpg', 1),
(1, 'https://example.com/iphone12pro_2.jpg', 2),
(2, 'https://example.com/macbook_1.jpg', 1),
(2, 'https://example.com/macbook_2.jpg', 2),
(3, 'https://example.com/samsung_1.jpg', 1),
(4, 'https://example.com/ipad_1.jpg', 1),
(4, 'https://example.com/ipad_2.jpg', 2),
(5, 'https://example.com/canon_1.jpg', 1),
(6, 'https://example.com/airpods_1.jpg', 1),
(7, 'https://example.com/switch_1.jpg', 1),
(7, 'https://example.com/switch_2.jpg', 2);

-- Inserimento prenotazioni
INSERT INTO bookings (listing_id, buyer_id, seller_id, booking_status, booking_date, scheduled_pickup_date, completed_at) VALUES
(4, 4, 2, 'confirmed', NOW(), DATE_ADD(NOW(), INTERVAL 3 DAY), NULL),
(7, 4, 1, 'confirmed', DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 7 DAY), DATE_SUB(NOW(), INTERVAL 5 DAY));

-- Inserimento conversazioni
INSERT INTO conversations (listing_id, buyer_id, seller_id, last_message_at, unread_by_seller, unread_by_buyer) VALUES
(4, 4, 2, NOW(), FALSE, FALSE),
(1, 5, 1, NOW(), TRUE, FALSE),
(7, 4, 1, DATE_SUB(NOW(), INTERVAL 5 DAY), FALSE, FALSE);

-- Inserimento messaggi
INSERT INTO messages (conversation_id, sender_id, content, is_read, read_at, created_at) VALUES
(1, 4, "Ciao, è ancora disponibile l\'iPad?", TRUE, NOW(), DATE_SUB(NOW(), INTERVAL 2 DAY)),
(1, 2, "Sì, è disponibile! Posso consegnarlo domani.", TRUE, NOW(), DATE_SUB(NOW(), INTERVAL 1 DAY)),
(1, 4, "Perfetto! Mi aspetto alle 18:00.", TRUE, NOW(), NOW()),
(2, 5, "Salve, qual è il miglior orario per vederlo?", TRUE, NOW(), DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(2, 1, "Buonasera, sono disponibile dal lunedì al venerdì dalle 19:00.", FALSE, NULL, NOW()),
(3, 4, "Bellissimo, grazie mille!", TRUE, NOW(), DATE_SUB(NOW(), INTERVAL 5 DAY));

-- Inserimento valutazioni
INSERT INTO reviews (listing_id, reviewer_id, reviewed_user_id, rating, comment, created_at) VALUES
(7, 4, 1, 5, "Venditore affidabile, prodotto perfetto, consegna rapida!", DATE_SUB(NOW(), INTERVAL 4 DAY)),
(7, 1, 4, 5, "Buyer serio, pagamento puntuale, consigliato!", DATE_SUB(NOW(), INTERVAL 4 DAY)),
(3, 4, 2, 4, "Ottimo contatto, prodotto come descritto. Piccolo ritardo nella consegna.", DATE_SUB(NOW(), INTERVAL 20 DAY));

-- Inserimento preferiti
INSERT INTO favorites (user_id, listing_id) VALUES
(4, 1),
(4, 2),
(5, 1),
(5, 6);

-- Inserimento segnalazioni
INSERT INTO reports (reporter_id, listing_id, reported_user_id, reason, description, status) VALUES
(5, NULL, 4, 'spam', 'Utente invia messaggi ripetuti', 'open'),
(4, 3, NULL, 'inappropriate', 'Le foto sono poco appropriate', 'reviewing');
