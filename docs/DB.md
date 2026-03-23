# ReuseIT - Marketplace per Elettronica Usata

**ReuseIT** è un'applicazione web che funziona come marketplace per l'usato elettronico. Gli utenti possono pubblicare annunci per vendere dispositivi usati, prenotarli e contattare il venditore via chat per coordinarsi sul ritiro fisico. Gli annunci vengono visualizzati su una mappa geografica.

---

## 📋 Indice

1. [Struttura del Progetto](#struttura-del-progetto)
2. [Panoramica Database](#panoramica-database)
3. [Schema Database Completo](#schema-database-completo)
4. [Script MySQL](#script-mysql)
5. [Relazioni e Workflow](#relazioni-e-workflow)
6. [Funzionalità Principali](#funzionalità-principali)
7. [Implementazione Frontend](#implementazione-frontend)

---

## Struttura del Progetto

```
ReuseIT/                              (cartella progetto)
├── public/                           (WEB ROOT - accessibile da browser)
│   ├── index.php                     (pagina principale)
│   ├── style.css                     (stili CSS)
│   ├── script.js                     (JavaScript frontend)
│   └── serve-image.php               (serve le immagini da ../uploads/)
│
├── api/                              (backend - NON direttamente accessibile)
│   └── upload.php                    (gestisce upload immagini)
│
├── uploads/                          (immagini caricate - NON direttamente accessibili)
│   ├── profile_pictures/             (foto profilo utenti)
│   └── listing_photos/               (foto annunci)
│
├── config/                           (configurazioni - NON accessibile web)
│   └── database.php                  (credenziali e connessione DB)
│
├── ReuseIT.sql                       (schema database)
├── ReuseIT.md                        (questo file)
└── README.md                         (documentazione)
```

---

## 🎯 Panoramica Database

### Caratteristiche Principali

| Aspetto | Descrizione |
|---------|------------|
| **Normalizzazione** | 3NF - Elimina ridondanze, ottimizzato per performance |
| **Indici Critici** | Coordinates GPS, status, user_id per query rapide |
| **Soft Delete** | Colonna `deleted_at` per GDPR e audit trail |
| **Timestamps** | `created_at`, `updated_at` su tutte le tabelle |
| **Vincoli FK** | Garantiscono integrità referenziale |
| **Estendibilità** | Facile aggiungere nuovi campi senza rompere la struttura |

### Tabelle Principali

- **users** - Profili utenti (seller/buyer)
- **categories** - Categorie di prodotto
- **listings** - Annunci pubblicati
- **listing_photos** - Foto degli annunci
- **bookings** - Prenotazioni degli annunci
- **conversations** - Chat tra buyer e seller
- **messages** - Messaggi singoli
- **reviews** - Valutazioni post-transazione
- **favorites** - Annunci salvati in wishlist
- **reports** - Segnalazioni

---

## 🗄️ Schema Database Completo

### TABELLA: users

```sql
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
  INDEX idx_coordinates (latitude, longitude)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Campi:**
- `id` - Identificativo univoco utente
- `email/password_hash` - Credenziali autenticazione
- `first_name, last_name` - Nome e cognome
- `phone_number` - Contatto telefonico
- `profile_picture_url` - Avatar profilo
- `bio` - Descrizione personale
- `address_*` - Indirizzo fisico
- `latitude, longitude` - Coordinate GPS per mappa
- `rating_average, rating_count` - Media valutazioni ricevute
- `is_verified` - Email/identità verificata
- `deleted_at` - Soft delete per GDPR

---

### TABELLA: categories

```sql
CREATE TABLE categories (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL UNIQUE,
  description TEXT,
  icon_url VARCHAR(500),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Categorie di esempio:**
- Smartphone
- Laptop
- Tablet
- Fotocamere
- Console
- Auricolari
- Smartwatch

---

### TABELLA: listings

```sql
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
  INDEX idx_seller_status (seller_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Stati Annuncio:**
- `active` - Disponibile per prenotazione
- `booked` - Già prenotato
- `completed` - Ritiro completato
- `cancelled` - Annullato

---

### TABELLA: listing_photos

```sql
CREATE TABLE listing_photos (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  listing_id BIGINT NOT NULL,
  photo_url VARCHAR(500) NOT NULL,
  display_order INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  
  INDEX idx_listing_id (listing_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### TABELLA: bookings

```sql
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
  
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
  
  INDEX idx_buyer_id (buyer_id),
  INDEX idx_seller_id (seller_id),
  INDEX idx_booking_status (booking_status),
  INDEX idx_booking_date (booking_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Caratteristiche:**
- Una sola prenotazione per annuncio (UNIQUE su listing_id)
- Status: `pending` → `confirmed` → (oppure `cancelled`)
- Data ritiro concordata in chat

---

### TABELLA: conversations

```sql
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
  
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
  
  UNIQUE KEY unique_conversation (listing_id, buyer_id, seller_id),
  INDEX idx_buyer_id (buyer_id),
  INDEX idx_seller_id (seller_id),
  INDEX idx_last_message_at (last_message_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Una conversazione per:** listing + buyer + seller

---

### TABELLA: messages

```sql
CREATE TABLE messages (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  conversation_id BIGINT NOT NULL,
  sender_id BIGINT NOT NULL,
  content TEXT NOT NULL,
  is_read BOOLEAN DEFAULT FALSE,
  read_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
  FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
  
  INDEX idx_conversation_id (conversation_id),
  INDEX idx_sender_id (sender_id),
  INDEX idx_created_at (created_at),
  INDEX idx_conversation_read (conversation_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### TABELLA: reviews

```sql
CREATE TABLE reviews (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  listing_id BIGINT NOT NULL,
  reviewer_id BIGINT NOT NULL,
  reviewed_user_id BIGINT NOT NULL,
  rating INT NOT NULL,
  comment TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (reviewed_user_id) REFERENCES users(id) ON DELETE CASCADE,
  
  UNIQUE KEY unique_review (listing_id, reviewer_id),
  INDEX idx_reviewed_user (reviewed_user_id),
  INDEX idx_reviewer_id (reviewer_id),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### TABELLA: favorites

```sql
CREATE TABLE favorites (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT NOT NULL,
  listing_id BIGINT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  
  UNIQUE KEY unique_favorite (user_id, listing_id),
  INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### TABELLA: reports

```sql
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
  
  FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE SET NULL,
  FOREIGN KEY (reported_user_id) REFERENCES users(id) ON DELETE SET NULL,
  
  INDEX idx_status (status),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 🗃️ Script MySQL

Copia questo script nel tuo client MySQL per creare il database completo con dati di test.

```sql
-- ============================================================
-- ReuseIT - Database Script MySQL
-- Marketplace per Elettronica Usata
-- ============================================================

-- DROP DATABASE IF EXISTS reuseit;
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
  INDEX idx_coordinates (latitude, longitude)
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
  INDEX idx_seller_status (seller_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== TABELLA FOTO ANNUNCI ====================
CREATE TABLE listing_photos (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  listing_id BIGINT NOT NULL,
  photo_url VARCHAR(500) NOT NULL,
  display_order INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  
  INDEX idx_listing_id (listing_id)
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
  
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
  
  INDEX idx_buyer_id (buyer_id),
  INDEX idx_seller_id (seller_id),
  INDEX idx_booking_status (booking_status),
  INDEX idx_booking_date (booking_date)
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
  
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
  
  UNIQUE KEY unique_conversation (listing_id, buyer_id, seller_id),
  INDEX idx_buyer_id (buyer_id),
  INDEX idx_seller_id (seller_id),
  INDEX idx_last_message_at (last_message_at)
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
  
  FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
  FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
  
  INDEX idx_conversation_id (conversation_id),
  INDEX idx_sender_id (sender_id),
  INDEX idx_created_at (created_at),
  INDEX idx_conversation_read (conversation_id, is_read)
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
  
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (reviewed_user_id) REFERENCES users(id) ON DELETE CASCADE,
  
  UNIQUE KEY unique_review (listing_id, reviewer_id),
  INDEX idx_reviewed_user (reviewed_user_id),
  INDEX idx_reviewer_id (reviewer_id),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== TABELLA PREFERITI ====================
CREATE TABLE favorites (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT NOT NULL,
  listing_id BIGINT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  
  UNIQUE KEY unique_favorite (user_id, listing_id),
  INDEX idx_user_id (user_id)
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
  
  FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE SET NULL,
  FOREIGN KEY (reported_user_id) REFERENCES users(id) ON DELETE SET NULL,
  
  INDEX idx_status (status),
  INDEX idx_created_at (created_at)
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
(1, 4, 'Ciao, è ancora disponibile l\'iPad?', TRUE, NOW(), DATE_SUB(NOW(), INTERVAL 2 DAY)),
(1, 2, 'Sì, è disponibile! Posso consegnarlo domani.', TRUE, NOW(), DATE_SUB(NOW(), INTERVAL 1 DAY)),
(1, 4, 'Perfetto! Mi aspetto alle 18:00.', TRUE, NOW(), NOW()),
(2, 5, 'Salve, qual è il miglior orario per vederlo?', TRUE, NOW(), DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(2, 1, 'Buonasera, sono disponibile dal lunedì al venerdì dalle 19:00.', FALSE, NULL, NOW()),
(3, 4, 'Bellissimo, grazie mille!', TRUE, NOW(), DATE_SUB(NOW(), INTERVAL 5 DAY));

-- Inserimento valutazioni
INSERT INTO reviews (listing_id, reviewer_id, reviewed_user_id, rating, comment, created_at) VALUES
(7, 4, 1, 5, 'Venditore affidabile, prodotto perfetto, consegna rapida!', DATE_SUB(NOW(), INTERVAL 4 DAY)),
(7, 1, 4, 5, 'Buyer serio, pagamento puntuale, consigliato!', DATE_SUB(NOW(), INTERVAL 4 DAY)),
(3, 4, 2, 4, 'Ottimo contatto, prodotto come descritto. Piccolo ritardo nella consegna.', DATE_SUB(NOW(), INTERVAL 20 DAY));

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
```

## 🔗 Relazioni e Workflow

### Diagramma delle Relazioni

```
┌─────────────────────────────────────────┐
│                 USERS                   │
│  (Profili, Valutazioni, Coordinate GPS) │
└─────────────────────────────────────────┘
         ↑        ↑         ↑         ↑
         │        │         │         │
    ┌────┴───┬────┴──┬──────┴──┬──────┴──┬──────────┐
    │         │       │         │         │          │
    ↓         ↓       ↓         ↓         ↓          ↓
LISTINGS  BOOKINGS REVIEWS  MESSAGES CONVERSATIONS FAVORITES
seller_id buyer_id reviewed reviews  buyer_id/     user_id
           seller_id user_id  sender_id seller_id
               │      │         │         │
               ↓      ↓         ↓         ↓
          listing_photos    conversions   (ref)
             (photo_url)    (struttura)
```

### Flusso di Utilizzo Completo

```
1️⃣ USER si registra
   ↓
   INSERT users (email, password_hash, first_name, ...)
   ↓
   Riceve: users.id

2️⃣ USER pubblica annuncio
   ↓
   INSERT listings (seller_id, category_id, title, ...)
   INSERT listing_photos (listing_id, photo_url, ...)
   ↓
   Listing visibile sulla mappa (latitude, longitude)

3️⃣ ALTRO USER vede su mappa
   ↓
   SELECT listings WHERE status = 'active'
   ↓
   Visualizza marker con coordinate

4️⃣ Clicca annuncio
   ↓
   SELECT listings + listing_photos + seller info
   ↓
   Vede dettagli e valutazioni seller

5️⃣ Clicca "Prenota"
   ↓
   INSERT bookings (status = 'pending')
   UPDATE listings (status = 'booked')
   INSERT conversations
   ↓
   Annuncio tolto dal marketplace

6️⃣ Chat aperta
   ↓
   SELECT messages FROM conversations
   ↓
   Buyer e seller si accordano su data/ora ritiro

7️⃣ Ritiro completato
   ↓
   UPDATE bookings (completed_at = NOW())
   UPDATE listings (status = 'completed')
   ↓
   Diventa disponibile scrivere review

8️⃣ Scrive valutazione
   ↓
   INSERT reviews (rating, comment)
   ↓
   UPDATE users.rating_average
```

---

## 🎯 Funzionalità Principali

### 1. GESTIONE UTENTI

#### Login / Registrazione
```sql
-- REGISTRAZIONE
INSERT INTO users (email, password_hash, first_name, last_name, ...)
VALUES ('new@email.com', '$hashed', 'Nome', 'Cognome', ...);

-- LOGIN
SELECT id, password_hash FROM users WHERE email = 'user@email.com';
```

#### Profilo Utente
```sql
-- VISUALIZZA PROFILO
SELECT id, first_name, last_name, email, bio, profile_picture_url,
       rating_average, rating_count, is_verified
FROM users WHERE id = 1;

-- STATISTICHE
SELECT 
  COUNT(DISTINCT CASE WHEN status = 'active' THEN id END) as active_listings,
  COUNT(DISTINCT CASE WHEN status = 'completed' THEN id END) as sold_items
FROM listings WHERE seller_id = 1;
```

#### Modifica Profilo
```sql
UPDATE users SET
  bio = 'Nuovo testo',
  profile_picture_url = 'https://...',
  address_city = 'Roma'
WHERE id = 1;
```

---

### 2. MAPPA E ANNUNCI

#### Visualizza Annunci su Mappa
```sql
-- Tutti gli annunci ATTIVI con coordinate
SELECT l.id, l.title, l.price, l.latitude, l.longitude,
       u.first_name, u.rating_average, c.name as category
FROM listings l
JOIN users u ON l.seller_id = u.id
JOIN categories c ON l.category_id = c.id
WHERE l.status = 'active';
```

#### Filtra per Area Geografica
```sql
-- Annunci entro 15km da coordinate (Milano)
SELECT l.id, l.title, l.price,
  (6371 * acos(cos(radians(45.4642)) * cos(radians(l.latitude)) *
   cos(radians(l.longitude) - radians(9.1900)) +
   sin(radians(45.4642)) * sin(radians(l.latitude)))) AS distance_km
FROM listings l
WHERE l.status = 'active'
HAVING distance_km < 15
ORDER BY distance_km ASC;
```

#### Dettagli Annuncio
```sql
-- Tutte le info dell'annuncio
SELECT l.*, u.first_name, u.rating_average, c.name
FROM listings l
JOIN users u ON l.seller_id = u.id
JOIN categories c ON l.category_id = c.id
WHERE l.id = 4;

-- Foto dell'annuncio
SELECT photo_url FROM listing_photos
WHERE listing_id = 4 ORDER BY display_order;
```

#### Pubblica Annuncio
```sql
-- Step 1: Inserisci annuncio
INSERT INTO listings (seller_id, category_id, title, description, 
                      price, brand, model, condition, latitude, longitude, ...)
VALUES (1, 1, 'iPhone 13', '...', 650, 'Apple', 'iPhone 13', 'Ottimo', 45.46, 9.19);

-- Step 2: Inserisci foto
INSERT INTO listing_photos (listing_id, photo_url, display_order)
VALUES (LAST_INSERT_ID(), 'https://...', 1);
```

---

### 3. CHAT

#### Workflow Chat Completo

```
User vede annuncio → Clicca "Contatta" 
  ↓
Sistema crea CONVERSAZIONE (se non esiste)
  ↓
User invia primo MESSAGGIO
  ↓
Ricevente vede notifica "Nuovo messaggio"
  ↓
Apre conversazione e legge
  ↓
Si accordano su data/ora ritiro via chat
```

#### Crea Conversazione
```sql
-- Step 1: Verifica se esiste
SELECT id FROM conversations
WHERE listing_id = 4 AND buyer_id = 5 AND seller_id = 2;

-- Step 2: Se non esiste, crea
INSERT INTO conversations (listing_id, buyer_id, seller_id)
VALUES (4, 5, 2);

-- Step 3: Primo messaggio
INSERT INTO messages (conversation_id, sender_id, content)
VALUES (LAST_INSERT_ID(), 5, 'Ciao! Sei interessato?');

-- Step 4: Aggiorna timestamp
UPDATE conversations SET last_message_at = NOW()
WHERE id = LAST_INSERT_ID();
```

#### Carica Conversazioni di un Utente
```sql
-- Chat dell'utente (come BUYER)
SELECT c.id, l.title, l.price,
       u_seller.first_name, u_seller.profile_picture_url,
       (SELECT content FROM messages WHERE conversation_id = c.id 
        ORDER BY created_at DESC LIMIT 1) as last_message,
       c.unread_by_buyer as unread
FROM conversations c
JOIN listings l ON c.listing_id = l.id
JOIN users u_seller ON l.seller_id = u_seller.id
WHERE c.buyer_id = 5
ORDER BY c.last_message_at DESC;
```

#### Carica Messaggi di una Conversazione
```sql
-- Messaggi della chat
SELECT m.id, m.sender_id, m.content, m.is_read, m.created_at,
       u.first_name, u.profile_picture_url
FROM messages m
JOIN users u ON m.sender_id = u.id
WHERE m.conversation_id = 1
ORDER BY m.created_at ASC;

-- Segna come letti
UPDATE messages 
SET is_read = TRUE, read_at = NOW()
WHERE conversation_id = 1 AND sender_id != 5;

-- Aggiorna conversazione
UPDATE conversations SET unread_by_buyer = FALSE
WHERE id = 1;
```

#### Invia Nuovo Messaggio
```sql
-- Inserisci messaggio
INSERT INTO messages (conversation_id, sender_id, content)
VALUES (1, 5, 'Perfetto! Ti aspetto domani alle 19:00');

-- Aggiorna timestamp
UPDATE conversations
SET last_message_at = NOW(), unread_by_seller = TRUE
WHERE id = 1;
```

---

### 4. PRENOTAZIONI E RITIRO

#### Workflow Prenotazioni

```
BUYER clicca "PRENOTA" (listing.status = 'active')
  ↓
Sistema crea BOOKING (status = 'pending')
Sistema crea CONVERSAZIONE
listings.status → 'booked'
  ↓
BUYER e SELLER chattano via MESSAGES
Si accordano su data/ora ritiro
  ↓
Ritiro completato
BOOKING.completed_at = NOW()
listings.status → 'completed'
  ↓
BUYER scrive REVIEW
users.rating_average aggiornato
```

#### Prenota Annuncio
```sql
-- Step 1: Verifica disponibilità
SELECT id FROM listings WHERE id = 4 AND status = 'active';

-- Step 2: Crea booking
INSERT INTO bookings (listing_id, buyer_id, seller_id, booking_status)
VALUES (4, 5, 2, 'pending');

-- Step 3: Aggiorna listing
UPDATE listings SET status = 'booked' WHERE id = 4;

-- Step 4: Crea conversazione
INSERT INTO conversations (listing_id, buyer_id, seller_id)
VALUES (4, 5, 2);
```

#### Cancella Prenotazione
```sql
-- Cancella booking
UPDATE bookings SET booking_status = 'cancelled'
WHERE id = 1;

-- Riporta listing disponibile
UPDATE listings SET status = 'active'
WHERE id = (SELECT listing_id FROM bookings WHERE id = 1);
```

#### Completa Ritiro
```sql
-- Dopo accordo in chat
UPDATE bookings
SET booking_status = 'confirmed', completed_at = NOW()
WHERE id = 1;

UPDATE listings SET status = 'completed'
WHERE id = 4;
```

#### Visualizza Prenotazioni (Seller)
```sql
-- Annunci che ho venduto
SELECT b.id, b.booking_status, b.scheduled_pickup_date,
       l.title, l.price,
       u.first_name, u.phone_number, u.rating_average
FROM bookings b
JOIN listings l ON b.listing_id = l.id
JOIN users u ON b.buyer_id = u.id
WHERE b.seller_id = 1
ORDER BY b.booking_date DESC;
```

#### Visualizza Prenotazioni (Buyer)
```sql
-- Annunci che ho prenotato
SELECT b.id, b.booking_status, b.scheduled_pickup_date,
       l.title, l.price,
       u.first_name, u.phone_number, u.rating_average
FROM bookings b
JOIN listings l ON b.listing_id = l.id
JOIN users u ON l.seller_id = u.id
WHERE b.buyer_id = 5
ORDER BY b.booking_date DESC;
```

---

### 5. VALUTAZIONI

#### Scrivi Valutazione
```sql
-- Step 1: Verifica ritiro completato
SELECT b.id FROM bookings b
WHERE b.listing_id = 4 AND b.buyer_id = 5 AND b.completed_at IS NOT NULL;

-- Step 2: Inserisci review
INSERT INTO reviews (listing_id, reviewer_id, reviewed_user_id, rating, comment)
VALUES (4, 5, 2, 5, 'Venditore affidabile!');

-- Step 3: Aggiorna rating medio
UPDATE users
SET rating_average = (SELECT AVG(rating) FROM reviews WHERE reviewed_user_id = 2),
    rating_count = (SELECT COUNT(*) FROM reviews WHERE reviewed_user_id = 2)
WHERE id = 2;
```

#### Visualizza Valutazioni di un Utente
```sql
SELECT r.rating, r.comment, r.created_at,
       u.first_name, u.profile_picture_url,
       l.title
FROM reviews r
JOIN users u ON r.reviewer_id = u.id
JOIN listings l ON r.listing_id = l.id
WHERE r.reviewed_user_id = 2
ORDER BY r.created_at DESC;
```

---

## 🎨 Implementazione Frontend

### 1. Pagina Login/Registrazione

```
LOGIN PAGE
├─ Form: email, password
├─ Query: SELECT id, password_hash FROM users WHERE email = ?
├─ Se valido: salva users.id in localStorage
└─ Redirect: Dashboard

REGISTRAZIONE PAGE
├─ Form: email, password, first_name, last_name, phone_number, address_city
├─ INSERT users con dati
├─ Set latitude/longitude da indirizzo (geocoding)
└─ Redirect: Completa Profilo
```

---

### 2. Profilo Utente

```
PROFILO PAGE
├─ Query: SELECT * FROM users WHERE id = logged_user_id
├─ Mostra:
│  ├─ Foto profilo (profile_picture_url)
│  ├─ Nome, cognome, bio
│  ├─ Valutazione media (rating_average, rating_count)
│  ├─ Badge "Verificato" (is_verified)
│  ├─ Numero annunci attivi/venduti
│  └─ Ultimo accesso
├─ Button "Modifica": form per UPDATE users
├─ Button "Carica foto": file upload
└─ Sezione Valutazioni:
   └─ Lista review ricevute con ratings

EDIT PROFILO
├─ Form pre-compilato con dati attuali
├─ Campo bio (textarea)
├─ Upload foto profilo
├─ Form indirizzo con picker mappa
└─ Save → UPDATE users
```

---

### 3. Mappa e Annunci

```
MAPPA PAGE
├─ Mappa interattiva (Google Maps/Leaflet)
├─ Query: SELECT * FROM listings WHERE status = 'active'
│        con latitude, longitude
├─ Marker per ogni annuncio
├─ Click marker → Card preview:
│  ├─ Foto (prima listing_photo)
│  ├─ Titolo, prezzo
│  ├─ Seller info + rating
│  └─ Button "Vedi dettagli"
├─ Filtri sidebar:
│  ├─ Categoria (SELECT DISTINCT category_id FROM listings)
│  ├─ Prezzo range (slider)
│  ├─ Distanza dal mio punto (geolocation)
│  └─ Condizione (Ottimo, Buono, Accettabile, Scarso)
└─ Ricerca per keyword (titolo, description)

LISTING DETAIL PAGE
├─ Query: SELECT * FROM listings WHERE id = ?
├─ Query: SELECT * FROM listing_photos WHERE listing_id = ? ORDER BY display_order
├─ Mostra:
│  ├─ Carousel foto (foto 1, 2, 3...)
│  ├─ Titolo, prezzo, descrizione
│  ├─ Dettagli specifici:
│  │  ├─ Brand, model, year
│  │  ├─ Condizione
│  │  ├─ Accessori inclusi
│  │  └─ Data pubblicazione
│  ├─ Localizzazione:
│  │  ├─ Indirizzo (location_address)
│  │  ├─ Mappa piccola (latitude, longitude)
│  │  └─ Distanza da me
│  ├─ Seller info (card):
│  │  ├─ Foto profilo
│  │  ├─ Nome, rating, numero valutazioni
│  │  ├─ Badge "Verificato"
│  │  └─ Button "Visualizza profilo"
│  ├─ View count (numero visualizzazioni)
│  └─ Pulsanti azioni:
│     ├─ "Prenota" → Modal prenotazione
│     ├─ "Contatta venditore" → Chat
│     ├─ "Aggiungi ai favoriti" → INSERT favorites
│     └─ "Segnala" → Modal report
└─ Ultime valutazioni (3-5 reviews)

PUBLISH ANNUNCIO PAGE
├─ Form passo-passo:
│  ├─ Passo 1: Categoria
│  │  └─ SELECT categories, scegli categoria
│  ├─ Passo 2: Dettagli base
│  │  ├─ Title (input)
│  │  ├─ Description (textarea)
│  │  ├─ Price (number)
│  │  └─ Condition (select: Ottimo, Buono, Accettabile, Scarso)
│  ├─ Passo 3: Dettagli prodotto
│  │  ├─ Brand (input)
│  │  ├─ Model (input)
│  │  ├─ Year (number)
│  │  └─ Accessories (multi-select)
│  ├─ Passo 4: Foto
│  │  └─ Upload multiplo (drag & drop)
│  │  └─ Ordina per importanza (drag to reorder)
│  ├─ Passo 5: Localizzazione
│  │  ├─ Map picker (clicca sulla mappa)
│  │  ├─ Auto-fill indirizzo da geocoding
│  │  └─ Mostra coordinate (latitude, longitude)
│  └─ Passo 6: Revisione
│     ├─ Preview completo
│     └─ Button "Pubblica"
└─ Submit → INSERT listings + INSERT listing_photos
```

---

### 4. Chat

```
INBOX CHAT (Left Sidebar)
├─ Query: SELECT * FROM conversations 
│         WHERE buyer_id = ? OR seller_id = ?
│         ORDER BY last_message_at DESC
├─ For each conversation mostra:
│  ├─ Foto listing (prima photo)
│  ├─ Titolo listing
│  ├─ Ultimo messaggio (preview + sender)
│  ├─ Timestamp ultimo messaggio
│  ├─ Badge "unread" se ci sono messaggi non letti
│  │  ├─ Se viewer è buyer: unread_by_buyer = TRUE
│  │  └─ Se viewer è seller: unread_by_seller = TRUE
│  └─ Click → Apri chat detail
├─ Search conversazioni
└─ Real-time update con WebSocket

CHAT DETAIL PAGE (Main Area)
├─ Header chat:
│  ├─ Foto listing + titolo
│  ├─ Nome seller/buyer
│  ├─ Status prenotazione (se esiste)
│  └─ Data ritiro prevista (se scheduled)
├─ Messaggi (zona scrollabile):
│  ├─ Query: SELECT * FROM messages WHERE conversation_id = ? ORDER BY created_at
│  ├─ For each message mostra:
│  │  ├─ Foto profilo sender
│  │  ├─ Nome sender
│  │  ├─ Contenuto messaggio
│  │  ├─ Timestamp
│  │  └─ Badge "letto" se is_read = TRUE
│  └─ Messaggi: align left se da altri, right se da me
├─ Input messaggio:
│  ├─ Textarea per testo
│  ├─ Button "Invia"
│  │  └─ INSERT messages + UPDATE conversations.last_message_at
│  ├─ Indica "Typing..." real-time
│  └─ Mostra "Online/Offline" status
├─ Quick actions:
│  ├─ "Fissa data ritiro" → Modal con date picker
│  │  └─ UPDATE bookings.scheduled_pickup_date
│  ├─ "Segna come completato" (solo seller dopo data)
│  │  └─ UPDATE bookings.completed_at
│  └─ "Segnala utente" → Modal report
└─ Real-time (WebSocket):
   ├─ Nuovo messaggio ricevuto
   ├─ Stato "letto" del messaggio
   └─ Notifiche badge
```

---

### 5. Prenotazioni

```
PRENOTA MODAL
├─ Mostra summary annuncio:
│  ├─ Foto
│  ├─ Titolo, prezzo
│  └─ Seller info
├─ Form prenotazione:
│  ├─ Opzionale: data ritiro desiderata (date picker)
│  ├─ Note/richieste iniziali (textarea)
│  └─ Checkbox "Ho letto le condizioni"
├─ Button "Conferma prenotazione"
│  └─ INSERT bookings + UPDATE listings + INSERT conversations
├─ Messaggio di conferma
└─ Button "Vai alla chat" → Redirect chat

LE MIE PRENOTAZIONI (BUYER VIEW)
├─ Tabs:
│  ├─ "In attesa" (booking_status = 'pending')
│  │  ├─ Query: SELECT * FROM bookings WHERE buyer_id = ? AND booking_status = 'pending'
│  │  ├─ Card per ogni prenotazione:
│  │  │  ├─ Annuncio info (foto, titolo, prezzo)
│  │  │  ├─ Seller info
│  │  │  ├─ Status badge
│  │  │  └─ Buttons:
│  │  │     ├─ "Contatta seller" → Chat
│  │  │     └─ "Annulla prenotazione" → Modal confirm
│  │  └─ Messaggio "Contatta il venditore per accordarvi"
│  │
│  ├─ "Confermate" (booking_status = 'confirmed', completed_at IS NULL)
│  │  ├─ Card per ogni prenotazione:
│  │  │  ├─ Annuncio info
│  │  │  ├─ Data ritiro prevista (scheduled_pickup_date)
│  │  │  ├─ Status badge
│  │  │  └─ Buttons:
│  │  │     ├─ "Contatta seller"
│  │  │     └─ "Segna come ritirato" → UPDATE bookings.completed_at
│  │  └─ Reminder: "Accordati sul ritiro"
│  │
│  └─ "Completate" (completed_at IS NOT NULL)
│     ├─ Card per ogni prenotazione:
│     │  ├─ Annuncio info
│     │  ├─ Data ritiro effettivo (completed_at)
│     │  └─ Buttons:
│     │     ├─ "Scrivi valutazione" (se non già scritto)
│     │     └─ "Visualizza valutazione" (se già scritto)
│     └─ Messaggio "Ritiro completato"
└─ Statistiche:
   └─ Total acquisti, total speso, rating ricevuti

ANNUNCI VENDUTI (SELLER VIEW)
├─ Query: SELECT * FROM bookings WHERE seller_id = ?
├─ Tabs similari a buyer ma con azioni seller:
│  ├─ "In attesa" (pending)
│  │  ├─ Card buyer info + booking status
│  │  ├─ Buttons:
│  │  │  ├─ "Contatta buyer"
│  │  │  └─ "Cancella prenotazione"
│  │  └─ Reminder: "Aspetta conferma dal buyer"
│  │
│  ├─ "Confermate" (confirmed, not completed)
│  │  ├─ Data ritiro prevista
│  │  ├─ Button:
│  │  │  ├─ "Contatta buyer"
│  │  │  └─ "Segna come ritirato" → UPDATE bookings.completed_at
│  │  └─ Reminder: "Aspetta il ritiro"
│  │
│  └─ "Completate" (completed)
│     ├─ Data ritiro effettivo
│     ├─ Valutazione ricevuta dal buyer (se scritto)
│     └─ Button: "Visualizza valutazione"
└─ Statistiche:
   └─ Total vendite, total incassato, rating ricevuti
```

---

### 6. Valutazioni

```
SCRIVI VALUTAZIONE (POST-RITIRO)
├─ Modal/Page disponibile solo se:
│  └─ bookings.completed_at IS NOT NULL AND review non ancora scritta
├─ Form:
│  ├─ Mostra annuncio + utente valutato
│  ├─ Rating: 5 stelle (click interattivo)
│  ├─ Comment: textarea (opzionale ma consigliato)
│  ├─ Anonymo: checkbox "Nascondi il mio nome" (opzionale)
│  └─ Button "Invia valutazione"
│     └─ INSERT reviews + UPDATE users.rating_average
├─ Feedback: "Grazie per la valutazione!"
└─ Suggerimento: "Guadagna la fiducia della comunità con valutazioni sincere"

PROFILO UTENTE - SEZIONE VALUTAZIONI
├─ Header statistiche:
│  ├─ Rating medio (es. 4.8/5)
│  ├─ Numero totale valutazioni (es. 12)
│  ├─ Distribuzione stelle (visivo istogramma):
│  │  ├─ 5 stelle: 10 valutazioni
│  │  ├─ 4 stelle: 2 valutazioni
│  │  ├─ 3 stelle: 0 valutazioni
│  │  ├─ 2 stelle: 0 valutazioni
│  │  └─ 1 stella: 0 valutazioni
│  └─ Badge affidabilità (es. "Venditore affidabile")
├─ Lista valutazioni:
│  ├─ Query: SELECT * FROM reviews WHERE reviewed_user_id = ?
│  │         ORDER BY created_at DESC
│  ├─ For each review mostra:
│  │  ├─ Nome reviewer (o "Anonimo" se nascosto)
│  │  ├─ Foto profilo reviewer
│  │  ├─ Rating (5 stelle visuali)
│  │  ├─ Testo commento
│  │  ├─ Data (es. "3 giorni fa")
│  │  └─ Annuncio object (titolo, foto)
│  └─ Pagina valutazioni (0-5 per pagina)
├─ Filtri:
│  ├─ Rating: 5, 4, 3, 2, 1 stelle
│  └─ Ordina: Recenti, Vecchi, Rating alto/basso
└─ Messaggio "Nessuna valutazione" se 0 reviews
```

---

## 📝 Riassunto Dati di Test Inclusi

Il database include esempi realistici:

- **5 utenti** con profili completi, rating diversi, verifiche
- **7 annunci** in categorie varie (Smartphone, Laptop, Tablet, etc.)
- **11 foto** di annunci
- **2 prenotazioni** (una in attesa, una completata)
- **3 conversazioni** con messaggi di esempio
- **3 valutazioni** tra buyer e seller
- **4 favoriti** (wishlist)
- **2 segnalazioni** per moderazione

Perfetto per testare l'intero flusso dell'applicazione!

---

**Creato: 2026 | ReuseIT Marketplace**
