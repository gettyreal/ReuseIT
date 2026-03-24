# ReuseIT Setup Guide

## Prerequisites

- PHP 8.0+ with mod_rewrite enabled
- MySQL 5.7+ or MariaDB
- Apache web server (or PHP built-in server for development)
- Composer (PHP dependency manager)

## Installation Steps

### 1. Clone the repository

```bash
git clone <repository-url>
cd ReuseIT
```

### 2. Install PHP Dependencies with Composer

The project uses **PHP Composer** to manage dependencies, including **PHP Dotenv** for environment configuration.

#### Option A: Using System Composer (Recommended)

If you have Composer installed globally:

```bash
composer install
```

#### Option B: Using Local Composer

If Composer is not installed on your system, download and run it locally:

```bash
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php
php composer.phar install
rm composer-setup.php
```

**What gets installed:**
- `vlucas/phpdotenv` (v5.6.3) — Environment variable loader
- `phpunit/phpunit` (v9.6.34) — Testing framework
- 32 additional support packages
- **Total: 34 packages in `vendor/` directory**

**After installation:**
- ✅ `vendor/autoload.php` is generated (PHP PSR-4 autoloader)
- ✅ `vendor/` directory contains all dependencies (do not commit to Git)

### 3. Setup Environment Variables with Dotenv

Create `.env` file in the project root by copying the template:

```bash
cp .env.example .env
```

Edit `.env` with your actual values:

```bash
cat > .env << 'EOF'
# Database Configuration
DB_HOST=localhost
DB_NAME=reuseit
DB_USER=root
DB_PASSWORD=your_password_here

# External Service Configuration
GOOGLE_MAPS_API_KEY=your-google-maps-api-key-here
EOF
```

**Environment Variables Available:**

| Variable | Description | Default | Required |
|----------|-------------|---------|----------|
| `DB_HOST` | MySQL server hostname | `localhost` | Yes |
| `DB_NAME` | Database name | `reuseit` | Yes |
| `DB_USER` | MySQL username | `root` | Yes |
| `DB_PASSWORD` | MySQL password | (empty) | No |
| `GOOGLE_MAPS_API_KEY` | Google Maps Geocoding API key | (empty) | No (for geolocation) |

#### How Dotenv Works

1. **Composer** loads during application startup: `require_once 'vendor/autoload.php'`
2. **PHP Dotenv** is instantiated in `public/api.php` (lines 54-61)
3. `.env` file is read and variables are loaded into `$_ENV[]`
4. Your code accesses variables via: `$_ENV['DB_HOST']`, `$_ENV['DB_NAME']`, etc.

```php
// Inside public/api.php
if (class_exists('Dotenv\Dotenv')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->safeLoad();  // Load .env file
}

// Now in config/database.php or any controller:
$dbHost = $_ENV['DB_HOST'];      // "localhost"
$dbName = $_ENV['DB_NAME'];      // "reuseit"
```

**Important Notes:**
- ✅ `.env` is in `.gitignore` — never commit credentials to Git
- ✅ `.env.example` is committed — serves as a template for new developers
- ✅ Each developer has their own local `.env` with their credentials
- ✅ Production uses server environment variables, not `.env` file

### 4. Setup Database

Create a MySQL database and import the schema:

```bash
mysql -u root -p -e "CREATE DATABASE reuseit CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p reuseit < ReuseIT.sql
```

Verify your credentials in `.env` match your MySQL setup.

### 5. Enable Apache mod_rewrite

```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

Verify in Apache configuration:
```bash
grep -i "AllowOverride" /etc/apache2/apache2.conf
# Should be: AllowOverride All
```

### 6. Configure VirtualHost

Add to Apache configuration (`/etc/apache2/sites-available/reuseit.conf`):

```apache
<VirtualHost *:80>
    ServerName localhost
    ServerAlias reuseit.local
    
    DocumentRoot /path/to/ReuseIT/public
    
    <Directory /path/to/ReuseIT/public>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/reuseit-error.log
    CustomLog ${APACHE_LOG_DIR}/reuseit-access.log combined
</VirtualHost>
```

Enable and restart:
```bash
sudo a2ensite reuseit
sudo systemctl restart apache2
```

## Testing

### Test Health Endpoint (no database required)

```bash
curl http://localhost:8020/api/health
```

Expected response:
```json
{
  "success": true,
  "data": {
    "status": "ok",
    "timestamp": "2026-03-23T20:40:18+00:00",
    "server": {
      "name": "hostname",
      "php_version": "8.2.29",
      "memory_usage": 2097152,
      "memory_limit": "128M"
    }
  }
}
```

### Test with Database Connection

Once database is configured and `.env` credentials are correct:

```bash
curl http://localhost:8020/api/listings
```

Response depends on authentication and database state.

### Verify Dotenv is Loaded

Test that environment variables are loaded correctly:

```bash
php -r "
require 'vendor/autoload.php';
\$dotenv = Dotenv\Dotenv::createImmutable('.');
\$dotenv->load();
echo 'DB_HOST: ' . (\$_ENV['DB_HOST'] ?? 'NOT SET') . PHP_EOL;
echo 'DB_NAME: ' . (\$_ENV['DB_NAME'] ?? 'NOT SET') . PHP_EOL;
"
```

Expected output:
```
DB_HOST: localhost
DB_NAME: reuseit
```

## Project Structure

```
ReuseIT/
├── .env                 # Environment variables (git-ignored)
├── .env.example         # Template for environment variables
├── .gitignore          # Excludes vendor/, .env, etc.
├── .htaccess           # Root Apache rewrite rules
├── composer.json       # Composer dependencies declaration
├── composer.lock       # Frozen dependency versions (reproducibility)
├── ReuseIT.sql         # Database schema
├── public/
│   ├── .htaccess       # Public folder rewrite rules
│   ├── api.php         # Front controller (entry point)
│   └── index.php       # Alternative entry point
├── src/
│   ├── Controllers/    # API controllers
│   ├── Repositories/   # Data access layer
│   ├── Services/       # Business logic
│   ├── Traits/         # Reusable traits (Softdeletable)
│   ├── Middleware/     # Request middleware
│   ├── Config/         # Configuration classes
│   └── Router.php      # HTTP router
├── config/
│   ├── database.php    # PDO configuration (uses $_ENV)
│   └── session.php     # Session handler
├── docs/
│   ├── SETUP.md        # This file
│   ├── DB.md           # Database documentation
│   ├── backend.md      # Backend architecture
│   └── reuseit.md      # General overview
└── vendor/
    ├── autoload.php    # PSR-4 autoloader (auto-generated)
    ├── composer/       # Composer metadata
    ├── vlucas/phpdotenv/  # Dotenv library
    ├── phpunit/        # Testing framework
    └── ... (34 packages total, auto-generated)
```

## Routing

### How it works

1. Apache `.htaccess` rewrites clean URLs to query parameters
2. `/api/health` → `public/api.php?uri=/api/health`
3. Front controller (`public/api.php`) parses URI and routes to controller
4. Router matches URI patterns and dispatches to appropriate controller action

### Example routes

```
GET  /api/health              → HealthController::check()
GET  /api/listings            → ListingController::list()
GET  /api/listings/:id        → ListingController::show()
POST /api/listings            → ListingController::create()
PATCH /api/listings/:id       → ListingController::update()
DELETE /api/listings/:id      → ListingController::delete()
POST /api/auth/register       → AuthController::register()
POST /api/auth/login          → AuthController::login()
```

## Troubleshooting

| Issue | Solution |
|-------|----------|
| 404 Not Found | Enable mod_rewrite: `sudo a2enmod rewrite && sudo systemctl restart apache2` |
| 500 Server Error | Check PHP error log: `tail -f /var/log/apache2/error.log` |
| Database connection failed | 1) Verify `.env` credentials 2) Check MySQL is running 3) Run setup test above |
| Variables not loading | Run the Dotenv verification test above |
| Composer not found | Install via: `https://getcomposer.org/download/` or use local `composer.phar` |
| PHP version error | Requires PHP 8.0+. Check: `php -v` |
| vendor/ directory missing | Run: `composer install` to regenerate dependencies |

## Development

### Using PHP built-in server (local development only)

```bash
php -S localhost:8000 -t public
curl http://localhost:8000/?uri=/api/health
```

### Testing with curl

```bash
# Health check (no database)
curl -X GET http://localhost:8020/api/health

# With headers
curl -X GET http://localhost:8020/api/health \
  -H "Content-Type: application/json"

# Pretty print with jq
curl -s http://localhost:8020/api/health | jq .

# Test with authentication
curl -X GET http://localhost:8020/api/auth/me \
  -H "Content-Type: application/json"
```

## Git Workflow

### Files to Commit

```bash
# Essential for reproducible builds
git add composer.json composer.lock .gitignore

# Documentation
git add docs/

# Application code
git add src/ config/ public/
```

### Files NOT to Commit

```
vendor/                 # Auto-generated by: composer install
.env                    # Local credentials (use .env.example as template)
*.log                   # Log files
.DS_Store              # macOS files
.vscode/               # IDE files
```

### Typical commit for dependency changes

```bash
git add composer.json composer.lock
git commit -m "chore: Update PHP dependencies"
```

### For other developers cloning the repo

```bash
git clone <url>
cd ReuseIT
composer install          # Installs all 34 packages from composer.lock
cp .env.example .env      # Create local .env
# Edit .env with your credentials
php -S localhost:8000 -t public
```

