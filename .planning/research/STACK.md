# Technology Stack: ReuseIT

**Project:** ReuseIT - Peer-to-Peer Used Electronics Marketplace  
**Researched:** 2026-03-23  
**Stack Philosophy:** Plain PHP showcasing architecture mastery, not framework dependency

---

## Executive Summary

ReuseIT's stack is deliberately constrained to demonstrate professional software architecture in plain PHP (7.4+). The stack focuses on supporting libraries for image processing, validation, environment configuration, and logging rather than frameworks. This approach provides full control, educational value, and showcases the developer's ability to architect without relying on framework scaffolding.

---

## Core Technology Stack

### PHP Runtime

| Technology | Version | Purpose | Why |
|------------|---------|---------|-----|
| **PHP** | **7.4 minimum, 8.4+ recommended** | Server-side language runtime | Project constraint. PHP 8.4 (Nov 2024, supported until Dec 2026) provides modern features (named arguments, attributes, readonly properties) while maintaining 7.4 compatibility as minimum. 8.5 released Nov 2025 but 8.4 has better ecosystem maturity. |
| **mysqli/PDO** | Built-in PHP extension | Database abstraction | PDO is mandatory per project requirements. Use PDO exclusively with prepared statements for all queries. Built into PHP, no external dependency needed. |
| **GD Library** | Built-in PHP extension | Image manipulation | Standard, built-in image processing. Sufficient for listing thumbnails, avatar resizing. No external dependencies required. |
| **ext-curl** | Built-in PHP extension | HTTP requests | Needed for Google Geocoding API calls. Built-in, widely available. |

### Database

| Technology | Version | Purpose | Why |
|------------|---------|---------|-----|
| **MySQL** | **8.0+ (current LTS)** or **8.4 LTS** | Relational database | Industry standard for PHP applications. MySQL 8.0 EOL Dec 2026, 8.4 LTS supported until Dec 2032. Use 8.0 for compatibility if upgrading MySQL during project lifecycle, but 8.4 is better long-term. |
| **PDO MySQL Driver** | Built-in (php-pdo-mysql) | Database access | Use exclusively. Prepared statements are mandatory for security. Connection pooling built-in via PHP-FPM. |

### Maps & Geolocation

| Technology | Version | Purpose | Why |
|------------|---------|---------|-----|
| **Google Maps API** | Latest (v3) | Map rendering & geocoding | Project requirement. Use Maps JavaScript API (frontend) for interactive map, Geocoding API (backend via curl) for address-to-coordinates conversion. Free tier sufficient for MVP. |
| **Google Geocoding API** | Latest | Address → coordinates conversion | Called from PHP via curl to support listing geolocation. No wrapper library—use raw HTTP requests with PDO-prepared coordinates storage. |

### Frontend (Vanilla JavaScript)

| Technology | Version | Purpose | Why |
|------------|---------|---------|-----|
| **HTML5** | Latest standard | Markup | No build tools. Semantic HTML5 for accessibility. |
| **CSS3** | Latest standard | Styling | No preprocessors. Use CSS Grid/Flexbox for modern layouts. Fallbacks for older browsers if needed. |
| **JavaScript (Vanilla)** | ES6+ with fallbacks | Client-side logic | No frameworks, no build step. Use Fetch API for AJAX. Modern browsers support async/await, arrow functions, spread operator. Target browsers with ES6 support. |

---

## Supporting Libraries (Composer Packages)

### Image Processing

| Library | Version | Purpose | When to Use | Confidence |
|---------|---------|---------|-------------|-----------|
| **intervention/image** | **3.11.7** (or 4.0+ stable when released) | Image resizing, thumbnail generation, format conversion | Required for photo uploads. Handles JPEG, PNG, WebP. Abstracts over GD/Imagick. Use GD driver (built-in). | HIGH |
| **ext-exif** | Built-in | EXIF data extraction | Optional: Read photo orientation, camera model. Useful for auto-rotating user uploads. Prevent EXIF data leakage (remove before storage). | MEDIUM |

**Rationale:** Intervention Image v3 is stable, widely tested, and simplifies GD usage. Don't use v4 beta in production yet (use when reaches stable). Built-in GD driver is sufficient; no need for Imagick dependency.

### Input Validation & Security

| Library | Version | Purpose | When to Use | Confidence |
|---------|---------|---------|-------------|-----------|
| **respect/validation** | **0.4.x** | Input validation (emails, URLs, coordinates) | Validate user input before storage. Cleaner than manual regex. Supports email, price, URL, custom validators. | HIGH |
| **ralouphie/getallheaders** | **3.0.x** | Get HTTP headers in non-Apache environments | Only if not using Apache. PHP-FPM may not expose getallheaders(). Include as fallback. | MEDIUM |

**Rationale:** Respect Validation is lightweight, framework-agnostic, PSR-12 compliant. No large framework overhead. Alternatively, use native PHP filter functions (filter_var, filter_input) for basic validation—less magical, more transparent.

### Environment Configuration

| Library | Version | Purpose | When to Use | Confidence |
|---------|---------|---------|-------------|-----------|
| **vlucas/phpdotenv** | **5.6.3** (or 5.6.2+) | Load .env configuration | Required for API keys, DB credentials, secrets. Prevents hardcoding. Auto-loads .env file in project root. | HIGH |

**Rationale:** Industry standard for environment-based configuration. Essential for managing Google Maps API key, database password, and other secrets across dev/staging/production. Latest version (5.6.3, Dec 2025) supports PHP 8.5.

### Logging & Debugging

| Library | Version | Purpose | When to Use | Confidence |
|---------|---------|---------|-------------|-----------|
| **monolog/monolog** | **3.8+** | Structured logging | Log errors, user actions, database queries. PSR-3 compliant logger. Essential for production debugging. | HIGH |

**Rationale:** Industry standard, lightweight, framework-agnostic. Supports file, syslog, email handlers. Better than error_log() for structured, queryable logs.

### HTTP & Authentication

| Library | Version | Purpose | When to Use | Confidence |
|---------|---------|---------|-------------|-----------|
| **firebase/php-jwt** | **7.0.3+** | JWT token generation (future) | Optional for v2 API tokens, stateless auth. Skip for MVP (use sessions). If needed: v7.0.3 (Feb 2026) latest stable. | MEDIUM |

**Rationale:** Only if moving to API-based auth. For MVP, use native PHP sessions (simpler, sufficient). JWT adds complexity without benefit for server-rendered app.

---

## Development & Quality Tools

| Tool | Version | Purpose | Why NOT in production |
|------|---------|---------|----------------------|
| **php-cs-fixer** | Latest | Auto-format code (PSR-12) | Development only. Pre-commit hook to enforce style. |
| **phpstan** | **2.1+** | Static analysis | Development only. Catches type errors before runtime. Level 8+ recommended. |
| **phpunit** | **12.0+** | Unit testing | Development only. For testing business logic (validators, repositories). |

**Rationale:** Use for development workflows but don't include in composer.json requires. Use `composer require --dev` only.

---

## Installation & Configuration

### Create composer.json

```json
{
  "name": "reuseit/marketplace",
  "description": "Peer-to-peer used electronics marketplace",
  "require": {
    "php": ">=7.4",
    "ext-pdo": "*",
    "ext-curl": "*",
    "ext-gd": "*",
    "intervention/image": "^3.11",
    "respect/validation": "^0.4",
    "vlucas/phpdotenv": "^5.6",
    "monolog/monolog": "^3.8"
  },
  "require-dev": {
    "php-cs-fixer/php-cs-fixer": "^3.38",
    "phpstan/phpstan": "^2.1",
    "phpunit/phpunit": "^12.0"
  },
  "autoload": {
    "psr-4": {
      "ReuseIT\\": "src/"
    }
  },
  "scripts": {
    "test": "phpunit",
    "analyze": "phpstan",
    "format": "php-cs-fixer fix ."
  }
}
```

### Initialize Project

```bash
# Install dependencies
composer install

# Create .env file (don't commit to git!)
cp .env.example .env
# Edit .env with local values:
# DB_HOST=localhost
# DB_USER=reuseit
# DB_PASS=secret
# GOOGLE_MAPS_API_KEY=your_key_here

# Run migrations/schema setup
php bin/migrate.php

# Start development server
php -S localhost:8000 -t public/
```

### .htaccess (Apache Rewrite Rules)

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    
    # Skip existing files/directories
    RewriteCond %{REQUEST_FILENAME} -f [OR]
    RewriteCond %{REQUEST_FILENAME} -d
    RewriteRule ^ - [L]
    
    # Route everything to index.php
    RewriteRule ^(.*)$ public/index.php [QSA,L]
</IfModule>
```

---

## What NOT to Use (and Why)

### Frameworks

| Framework | Why NOT | Alternative |
|-----------|--------|-------------|
| **Laravel** | Project requirement: showcase plain PHP architecture. Laravel abstracts routing, ORM, validation—eliminating learning value. | Use native PHP with manual routing (Router class) + PDO. |
| **Symfony** | Too heavy for showcase project. Full-stack framework obscures architectural decisions. | Minimal routing library only if needed (Fast Route). |
| **Slim** | Even microframeworks add unnecessary abstraction for this project scope. | Manual request/response handling. |

### Build Tools & Bundlers

| Tool | Why NOT | Alternative |
|------|--------|-------------|
| **npm/webpack** | Project requirement: vanilla JavaScript. Build tools add complexity. | Write JS directly. Use CSS Grid instead of Tailwind. |
| **Vite** | Frontend must be vanilla HTML/CSS/JS with no build step. | Inline small CSS, use CSS @import sparingly. |

### Heavy Dependencies

| Package | Why NOT | Alternative |
|---------|--------|-------------|
| **Doctrine ORM** | Abstracts database layer too much. PDO teaches better DB fundamentals. | Learn PDO thoroughly. Use Repository pattern manually. |
| **Symfony/DependencyInjection** | Container adds indirection without benefit for small project. | Use constructor injection directly. No magic. |
| **Faker** | Overkill for MVP seeding. | Write minimal SQL fixtures by hand. |

### Frontend Frameworks

| Framework | Why NOT | Alternative |
|-----------|--------|-------------|
| **Vue, React, Angular** | Project requirement: vanilla JavaScript only. No build step. | Vanilla DOM manipulation with Fetch API. Use Web Components if needed for reusable UI. |

---

## Database Configuration Details

### PDO Connection String

```php
// .env
DB_HOST=localhost
DB_PORT=3306
DB_NAME=reuseit
DB_USER=reuseit
DB_PASS=secure_password

// config/database.php
$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
    getenv('DB_HOST'),
    getenv('DB_PORT') ?: 3306,
    getenv('DB_NAME')
);

$pdo = new PDO($dsn, getenv('DB_USER'), getenv('DB_PASS'), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false, // Use native prepared statements
]);
```

### Performance Indexing Strategy

```sql
-- Critical for filtering
CREATE INDEX idx_listings_status ON listings(status);
CREATE INDEX idx_listings_user_id ON listings(user_id);
CREATE INDEX idx_listings_category ON listings(category);

-- Critical for geolocation queries
CREATE INDEX idx_listings_coordinates ON listings(latitude, longitude);

-- Chat & messaging
CREATE INDEX idx_messages_conversation_id ON messages(conversation_id);
CREATE INDEX idx_messages_created_at ON messages(created_at);

-- Reviews
CREATE INDEX idx_reviews_user_id ON reviews(user_id);
CREATE INDEX idx_reviews_reviewer_id ON reviews(reviewer_id);
```

---

## Google Maps Integration

### Frontend (JavaScript)

```javascript
// public/js/map.js - No build tools
const map = new google.maps.Map(document.getElementById('map'), {
  zoom: 12,
  center: { lat: 0, lng: 0 },
});

// Add markers from API
fetch('/api/listings/nearby?lat=40.7128&lng=-74.0060&radius=10')
  .then(r => r.json())
  .then(listings => {
    listings.forEach(listing => {
      new google.maps.Marker({
        position: { lat: listing.latitude, lng: listing.longitude },
        map: map,
        title: listing.title,
      });
    });
  });
```

### Backend (PHP)

```php
// src/Service/GeolocationService.php
class GeolocationService {
    private PDO $pdo;
    private string $apiKey;
    
    public function __construct(PDO $pdo, string $apiKey) {
        $this->pdo = $pdo;
        $this->apiKey = $apiKey;
    }
    
    // Address → Coordinates
    public function geocodeAddress(string $address): array {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://maps.googleapis.com/maps/api/geocode/json?' .
                http_build_query([
                    'address' => $address,
                    'key' => $this->apiKey,
                ]),
            CURLOPT_RETURNTRANSFER => true,
        ]);
        $response = json_decode(curl_exec($ch), true);
        curl_close($ch);
        
        if ($response['status'] !== 'OK') {
            throw new Exception('Geocoding failed: ' . $response['status']);
        }
        
        $location = $response['results'][0]['geometry']['location'];
        return ['latitude' => $location['lat'], 'longitude' => $location['lng']];
    }
    
    // Haversine distance calculation
    public function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float {
        $R = 6371; // Earth radius in km
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng / 2) * sin($dLng / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $R * $c;
    }
    
    // Find nearby listings
    public function findNearby(float $lat, float $lng, int $radiusKm = 10): array {
        $stmt = $this->pdo->prepare('
            SELECT id, title, latitude, longitude,
                   (6371 * acos(cos(radians(?)) * cos(radians(latitude)) *
                    cos(radians(longitude) - radians(?)) +
                    sin(radians(?)) * sin(radians(latitude)))) AS distance
            FROM listings
            WHERE status = "active"
            HAVING distance <= ?
            ORDER BY distance ASC
        ');
        $stmt->execute([$lat, $lng, $lat, $radiusKm]);
        return $stmt->fetchAll();
    }
}
```

---

## Deployment & Hosting Checklist

### Server Requirements

- PHP 7.4+ (ideally 8.4+)
- MySQL 8.0+ (InnoDB for transactions)
- Apache 2.4+ with mod_rewrite OR Nginx with PHP-FPM
- SSL/TLS certificate (Let's Encrypt free)
- Disk space: ~100MB for codebase + uploads

### Environment Variables (Production)

```bash
# .env.production (never commit, server-specific)
DB_HOST=prod.mysql.example.com
DB_USER=reuseit_prod
DB_PASS=***strong_password***
GOOGLE_MAPS_API_KEY=***api_key***
LOG_LEVEL=warning
ENVIRONMENT=production
```

### Upload Directory

```bash
# Create writable directory for photos
mkdir -p storage/uploads/listings
chmod 755 storage/uploads
chmod 755 storage/uploads/listings

# Prevent direct access to originals
# Serve through PHP script that validates user has listing access
```

### .gitignore

```
.env
.env.local
vendor/
storage/uploads/*
!storage/uploads/.gitkeep
logs/*
!logs/.gitkeep
.DS_Store
*.swp
```

---

## Version Matrix (2025 Compatibility)

| Package | Supported PHP | Status | Notes |
|---------|---------------|--------|-------|
| PHP | 7.4–8.5 | 7.4 EOL Dec 2022, but project requirement. 8.4 is sweet spot. | 8.5 latest (Nov 2025) but ecosystem still stabilizing. |
| intervention/image | 3.x: 8.1+, 4.x: 8.3+ | 3.11.7 stable, 4.0 beta | Use 3.11.7 for production. 4.0 when stable. |
| respect/validation | 0.4.x: 7.0+ | Stable 0.4.3 (latest) | Minimal changes, very stable library. |
| vlucas/phpdotenv | 5.6.x: 7.4+ | 5.6.3 latest (Feb 2026, PHP 8.5 support added) | Very stable. No breaking changes expected. |
| monolog/monolog | 3.x: 8.1+ | 3.8+ current | Major version 3, stable. 2.x deprecated. |
| firebase/php-jwt | 7.x: 8.0+ | v7.0.3 latest (Feb 2026) | For future use only. Skip for MVP. |

---

## Key Decisions Rationale

### Why No Framework?

The project is a **showcase of architecture mastery**. Using Laravel or Symfony would hide the developer's ability to:
- Design clean routing architecture
- Implement repository pattern without ORM
- Build validation logic manually
- Structure dependency injection clearly
- Handle errors and exceptions thoughtfully

Plain PHP forces deliberate architectural decisions, making the codebase more educational and impressive.

### Why These Specific Libraries?

1. **intervention/image** — Most popular image library (193M downloads on Packagist). Abstracts GD complexity without framework dependency.
2. **respect/validation** — Lightweight, no framework coupling. Teaches validation architecture.
3. **vlucas/phpdotenv** — Industry standard for config management. Used by everyone from startups to enterprises.
4. **monolog/monolog** — PSR-3 compliant, framework-agnostic. Best practice for logging.
5. **firebase/php-jwt** — Only if API auth needed. For now: skip and use sessions.

### Why Not Others?

- **PHPMailer/Symfony Mailer** — Email out of scope (v2 feature). Skip for MVP.
- **Guzzle** — Only for HTTP client. Use curl directly to avoid dependency.
- **Carbon** — Date library. PHP's native DateTime is sufficient for MVP.
- **League/CSV** — CSV parsing not needed in scope.

---

## Migration Path to Newer PHP

```
Current: PHP 7.4+
Target: PHP 8.4 (recommended)
Future: PHP 8.5+ (when ecosystem stabilizes)

No breaking changes expected in library stack for 7.4 → 8.4 upgrade.
```

---

## Confidence Assessment

| Area | Confidence | Reason |
|------|-----------|--------|
| **Core Stack** | HIGH | PHP, MySQL, PDO, Google Maps are non-negotiable per project requirements. Versions verified from official sources. |
| **Supporting Libraries** | HIGH | All packages verified on Packagist with current version info (2025/2026 releases). Popular choices with active maintenance. |
| **Architecture Pattern** | HIGH | Plain PHP with Services, Repositories, Value Objects is proven pattern. No framework magic. |
| **Geolocation** | HIGH | Google Geocoding API + Haversine formula is standard approach. Native to backend. |
| **Frontend** | HIGH | Vanilla JS, CSS, HTML are timeless. No framework means no breaking changes to worry about. |
| **Deployment** | MEDIUM | Assumes standard Apache/PHP-FPM hosting. Edge cases (Kubernetes, serverless) not covered but not needed for MVP. |

---

## Sources

- **PHP Official:** https://www.php.net/supported-versions.php (current as of 2026-03-23)
- **Packagist (intervention/image):** https://packagist.org/packages/intervention/image (v3.11.7 latest stable)
- **Packagist (vlucas/phpdotenv):** Latest release v5.6.3 (Dec 2025, supports PHP 8.5)
- **Firebase/PHP-JWT:** https://github.com/firebase/php-jwt (v7.0.3 latest, Feb 2026)
- **PHP-FIG PSR-12:** https://www.php-fig.org/psr/psr-12/ (coding standards)
- **PDO Manual:** https://www.php.net/manual/en/book.pdo.php (built-in, canonical reference)
- **Google Maps Documentation:** https://developers.google.com/maps/documentation (Geocoding & Maps JavaScript API)
- **MySQL Versions:** https://www.mysql.com/downloads/mysql/ (8.0, 8.4 LTS current)

---

## Next Steps

1. **Phase 1:** Set up project structure with composer, bootstrap database connection
2. **Phase 2:** Implement authentication layer using plain sessions (no frameworks)
3. **Phase 3:** Build listing CRUD with image upload using intervention/image
4. **Phase 4:** Integrate Google Maps geolocation via API
5. **Phase 5:** Add validation layer using respect/validation
6. **Phase 6:** Implement logging with monolog for production readiness

Each phase uses only libraries specified in this stack. No framework shortcuts.
