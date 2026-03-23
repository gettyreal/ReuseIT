# ReuseIT Setup Guide

## Prerequisites

- PHP 8.0+ with mod_rewrite enabled
- MySQL 5.7+ or MariaDB
- Apache web server

## Installation Steps

### 1. Clone the repository

```bash
git clone <repository-url>
cd ReuseIT
```

### 2. Setup database

Create a MySQL database and import the schema:

```bash
mysql -u root -p -e "CREATE DATABASE reuseit CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p reuseit < ReuseIT.sql
```

### 3. Configure environment variables

Create `.env` in the project root:

```bash
cat > .env << 'EOF'
DB_HOST=localhost
DB_NAME=reuseit
DB_USER=root
DB_PASSWORD=your_password_here
EOF
```

**Variables:**
- `DB_HOST` — MySQL host (default: localhost)
- `DB_NAME` — Database name (default: reuseit)
- `DB_USER` — MySQL user (default: root)
- `DB_PASSWORD` — MySQL password (default: empty)

### 4. Enable Apache mod_rewrite

```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

Verify in Apache configuration:
```bash
grep -i "AllowOverride" /etc/apache2/apache2.conf
# Should be: AllowOverride All
```

### 5. Configure VirtualHost

Add to Apache configuration (`/etc/apache2/sites-available/reuseit.conf`):

```apache
<VirtualHost *:80>
    ServerName localhost
    ServerAlias reuseit.local
    
    DocumentRoot /path/to/ReuseIT
    
    <Directory /path/to/ReuseIT>
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

Once database is configured:

```bash
curl http://localhost:8020/api/listings
```

Response depends on authentication and database state.

## Project Structure

```
ReuseIT/
├── .env                 # Environment variables (git-ignored)
├── .htaccess           # Root Apache rewrite rules
├── ReuseIT.sql         # Database schema
├── public/
│   ├── .htaccess       # Public folder rewrite rules
│   └── index.php       # Front controller (entry point)
├── src/
│   ├── Controllers/    # API controllers
│   ├── Repositories/   # Data access layer
│   ├── Traits/         # Reusable traits (Softdeletable)
│   └── Router.php      # HTTP router
├── config/
│   ├── database.php    # PDO configuration
│   └── session.php     # Session handler
└── vendor/
    └── autoload.php    # PSR-4 autoloader
```

## Routing

### How it works

1. Apache `.htaccess` rewrites clean URLs to query parameters
2. `/api/health` → `public/index.php?uri=/api/health`
3. Front controller parses URI and routes to controller

### Example routes

```
GET  /api/health              → HealthController::check()
GET  /api/listings            → ListingController::list()
GET  /api/listings/:id        → ListingController::show()
POST /api/listings            → ListingController::create()
```

## Troubleshooting

| Issue | Solution |
|-------|----------|
| 404 Not Found | Enable mod_rewrite: `sudo a2enmod rewrite && sudo systemctl restart apache2` |
| 500 Server Error | Check PHP error log: `tail -f /var/log/apache2/error.log` |
| Database connection failed | Verify `.env` credentials and MySQL is running |
| Permission denied | Check file permissions: `chmod -R 755 /path/to/ReuseIT` |

## Development

### Using PHP built-in server (local development only)

```bash
php -S localhost:8000 public/index.php
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
```

## Next Steps

- Setup Phase 2 (Authentication) - user registration, login, JWT tokens
- Implement remaining endpoints per ROADMAP.md
- Configure production environment variables
