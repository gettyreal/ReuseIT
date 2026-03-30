# ReuseIT - Marketplace per Elettronica Usata

![Status](https://img.shields.io/badge/status-in%20development-orange)
![License](https://img.shields.io/badge/license-MIT-blue)
![PHP Version](https://img.shields.io/badge/php-7.4%2B-purple)
![Database](https://img.shields.io/badge/database-MySQL-blue)

**Dai nuova vita alla tecnologia. ReuseIT è il marketplace che dà una seconda chance all'elettronica.**

**ReuseIT** è una piattaforma web per la compravendita di dispositivi elettronici usati. Gli utenti possono pubblicare annunci, scoprire prodotti su una mappa interattiva, contattarsi via chat e completare transazioni basate sulla reputazione locale.

---

## 📑 Indice

- [Visione e Scopo](#-visione-e-scopo)
- [Obiettivi Principali](#-obiettivi-principali)
- [Caratteristiche Chiave](#-caratteristiche-chiave)
- [Stack Tecnologico](#%EF%B8%8F-stack-tecnologico)
- [Architettura Tecnica](#%EF%B8%8F-architettura-tecnica)
- [Struttura del Progetto](#-struttura-del-progetto)
- [Getting Started](#-getting-started)
- [Flusso Utente Completo](#-flusso-utente-completo)
- [Database Schema](#-database-schema)
- [API Endpoints](#-api-endpoints)
- [Sviluppo e Contribute](#-sviluppo-e-contribute)

---

## 🎯 Visione e Scopo

ReuseIT affronta il crescente problema dello spreco elettronico creando un ecosistema dove:

- **L'usato diventa risorsa**: Dispositivi ancora funzionanti trovano una seconda vita
- **La comunità locale è prioritaria**: Acquisti e vendite avvengono di persona, riducendo l'impronta logistica
- **La fiducia è misurabile**: Un sistema di valutazioni e reputazione costruisce comunità affidabili
- **La semplicità è essenziale**: Interfaccia intuitiva per utenti con qualsiasi competenza tecnologica

### Problema che Risolve

Molte persone hanno dispositivi elettronici ancora utilizzabili che vogliono vendere o acquistare a prezzo inferiore, ma le piattaforme esistenti:
- Richiedono spedizioni (costose, inquinanti, rischiose per la merce)
- Mancano di fiducia (sconosciuti, transazioni incerte)
- Sono disorganizzate (difficile trovare oggetti locali)
- Non incentivano la reputazione locale

**ReuseIT** centralizza queste necessità in un'unica esperienza coesa.

---

## 🎯 Obiettivi Principali

### Accessibilità
La piattaforma deve essere usabile da chiunque, indipendentemente dal livello tecnico o dalla posizione geografica.

### Località
Le transazioni sono basate su ritiro fisico di persona, creando connessioni autentiche nella comunità.

### Semplicità d'Uso
Pubblicare un annuncio o prenotarne uno deve richiedere pochi clic.

### Trasparenza
Valutazioni, prezzi, e condizioni dei prodotti sono chiaramente visibili.

### Sostenibilità
Ridurre lo spreco elettronico prolungando la vita utile dei dispositivi.

---

## ✨ Caratteristiche Chiave

### 🗺️ Mappa Interattiva
Visualizza annunci attivi su una mappa geografica. Scopri dispositivi disponibili vicino a te e filtra per categoria, prezzo, condizione e distanza.

### 📋 Gestione Annunci Completa
Pubblica, modifica, e gestisci facilmente i tuoi annunci con foto multiple, dettagli specifici del prodotto, e localizzazione precisa.

### 💬 Chat Integrata
Comunica direttamente con buyer/seller per accordarsi su data e luogo di ritiro. Cronologia messaggi e notifiche di lettura.

### ⭐ Sistema di Reputazione
Valutazioni 1-5 stelle post-transazione costruiscono reputazione verificabile. Visualizza il rating medio di ogni utente e leggi commenti dettagliati.

### 📱 Prenotazioni Strutturate
Workflow chiaro: prenota → contratta → ritira → valuta. Statuses trasparenti per buyer e seller.

### ❤️ Preferiti e Wishlist
Salva annunci interessanti per consultarli in un secondo momento.

### 🚨 Sistema di Moderazione
Segnala annunci inappropriati o utenti problematici. Dashboard di moderazione per admin.

---

## 🛠️ Stack Tecnologico

### Backend

| Componente | Scelta | Motivazione |
|-----------|--------|-------------|
| **Linguaggio** | PHP 7.4+ (Plain) | Controllo totale architettura, showcase project |
| **Database** | MySQL + PDO | Prepared statements per sicurezza SQL injection |
| **Autenticazione** | PHP Sessions | Stateless-optional, nativa, semplice |
| **Pattern** | Layered Architecture | Separazione netta delle responsabilità |
| **Validazione** | Value Objects | Logica di validazione incapsulata e riusabile |

### Frontend

| Componente | Scelta |
|-----------|--------|
| **HTML/CSS** | Vanilla (nessun framework CSS) |
| **JavaScript** | Vanilla ES6+ (Fetch API) |
| **Mappa** | Google Maps API |
| **State** | localStorage / sessionStorage |

### Database

| Aspetto | Descrizione |
|--------|------------|
| **Normalizzazione** | 3NF - Ottimizzato per query performance e consistency |
| **Soft Delete** | Conformità GDPR e audit trail |
| **Indici Critici** | Coordinates GPS, status, user_id per query veloci |
| **Timestamps** | created_at, updated_at automatici |

---

## 🏗️ Architettura Tecnica

ReuseIT segue un'architettura **layered** con separazione chiara delle responsabilità:

```
┌─────────────────────────────────────────┐
│  Browser (JavaScript + Fetch API)       │
└────────────────┬────────────────────────┘
                 │ REST API (JSON)
                 ↓
┌─────────────────────────────────────────┐
│  public/api.php (Front Controller)      │
│  - Routing, autoloading, error handling │
└────────────────┬────────────────────────┘
                 │
                 ↓
┌─────────────────────────────────────────┐
│  Controllers (HTTP Layer)               │
│  - Parse request, validate input        │
│  - Chiama Services, restituisce JSON    │
└────────────────┬────────────────────────┘
                 │
                 ↓
┌─────────────────────────────────────────┐
│  Services (Business Logic)              │
│  - Validazione Value Objects            │
│  - Orchestrazione Repositories          │
│  - Logica di dominio                    │
└────────────────┬────────────────────────┘
                 │
                 ↓
┌─────────────────────────────────────────┐
│  Repositories (Data Access Layer)       │
│  - Query SQL con PDO prepared           │
│  - Hydration entity da risultati        │
│  - Operazioni CRUD                      │
└────────────────┬────────────────────────┘
                 │
                 ↓
┌─────────────────────────────────────────┐
│  MySQL Database                         │
│  - 10 tabelle normalized, indici ottimi │
└─────────────────────────────────────────┘
```

### Flusso Request/Response Tipico

```
1. Browser: fetch('/api/listings?category=1')
2. .htaccess: rerouta a public/api.php?path=listings
3. Front controller: session_start(), autoload, istanzia Router
4. Router: dispatch('listings', 'GET') → ListingController::getAll()
5. Controller: chiama ListingService::getActiveListings($filters)
6. Service: valida filtri, chiama ListingRepository::findActive()
7. Repository: query SQL con PDO prepared statements
8. Response: JSON { success: true, data: [...], timestamp: "..." }
9. Browser: elabora JSON con JavaScript
```

---

## 📂 Struttura del Progetto

```
ReuseIT/
├── public/                              (WEB ROOT - accessibile via browser)
│   ├── index.php                        (Single Page Application)
│   ├── api.php                          (Front Controller API)
│   ├── .htaccess                        (Apache rewrite rules)
│   ├── style.css                        (Frontend styles)
│   └── script.js                        (Frontend JavaScript)
│
├── src/                                 (Backend code)
│   ├── Core/                            (Foundation layer)
│   │   ├── Database.php                 (Singleton PDO)
│   │   ├── Router.php                   (Route dispatcher)
│   │   ├── Request.php                  (HTTP input parser)
│   │   ├── Response.php                 (JSON response formatter)
│   │   └── Session.php                  (Session wrapper)
│   │
│   ├── Entities/                        (Domain models)
│   │   ├── User.php
│   │   ├── Listing.php
│   │   ├── Booking.php
│   │   ├── Message.php
│   │   └── ...
│   │
│   ├── Repositories/                    (Data access layer)
│   │   ├── UserRepository.php
│   │   ├── ListingRepository.php
│   │   └── ...
│   │
│   ├── Services/                        (Business logic layer)
│   │   ├── AuthService.php
│   │   ├── ListingService.php
│   │   ├── BookingService.php
│   │   └── ...
│   │
│   ├── Controllers/                     (HTTP handlers)
│   │   ├── AuthController.php
│   │   ├── ListingController.php
│   │   └── ...
│   │
│   ├── ValueObjects/                    (Validation logic)
│   ├── DTOs/                            (Data Transfer Objects)
│   ├── Middleware/                      (Request middleware)
│   └── Utils/                           (Helper functions)
│
├── uploads/                             (Image storage)
│   ├── profile_pictures/
│   └── listing_photos/
│
├── config/                              (Configuration - NON web-accessible)
│   ├── database.php
│   └── constants.php
│
├── docs/                                (Documentation)
│   ├── reuseit.md                       (Vision & Architecture)
│   ├── backend.md                       (Backend specifications)
│   ├── DB.md                            (Database & SQL)
│   └── ENDPOINTS.md                     (API endpoints)
│
├── .env.example                         (Environment template)
├── composer.json                        (PHP dependencies)
├── ReuseIT.sql                          (Database schema + test data)
└── README.md                            (questo file)
```

---

## 🚀 Getting Started

### Prerequisiti

- PHP 7.4 o superiore
- MySQL 5.7 o MariaDB 10.3+
- Apache con mod_rewrite abilitato
- Composer (opzionale, per dipendenze)

### Installazione

#### 1. Clona il Repository

```bash
git clone https://github.com/tuousername/ReuseIT.git
cd ReuseIT
```

#### 2. Configura il Database

```bash
# Crea database da ReuseIT.sql
mysql -u root -p < ReuseIT.sql
```

#### 3. Configura le Variabili d'Ambiente

```bash
cp .env.example .env

# Modifica .env con le tue credenziali
# DB_HOST, DB_USER, DB_PASS, DB_NAME, ecc.
```

#### 4. Installa Dipendenze

```bash
composer install
```

#### 5. Avvia il Server

```bash
# Opzione 1: PHP Built-in Server
php -S localhost:8000 -t public

# Opzione 2: Apache (configura VirtualHost)
# DocumentRoot = /path/to/ReuseIT/public
```

#### 6. Accedi all'Applicazione

```
http://localhost:8000
```

### Dati di Test

Il database includes sample data:
- **5 utenti** con profili completi
- **7 annunci** in diverse categorie
- **Prenotazioni, messaggi, valutazioni** di esempio

Credenziali test:
- Email: `luca.rossi@email.com`
- Password: (controlla ReuseIT.sql per l'hash)

---

## 👥 Flusso Utente Completo

### Ciclo Acquisto/Vendita Tipico

```
1️⃣ REGISTRAZIONE
   └─ Utente si registra con email, password, dati profilo
      ├─ Coordinate GPS da indirizzo
      └─ Login automatico

2️⃣ SELLER PUBBLICA ANNUNCIO
   └─ Form multi-step: categoria → dettagli → foto → localizzazione
      ├─ Upload foto multiple
      ├─ Scegli localizzazione su mappa
      └─ Annuncio live e visibile sulla mappa

3️⃣ BUYER SCOPRE ANNUNCIO
   └─ Naviga mappa con marker annunci attivi
      ├─ Filtri: categoria, prezzo, distanza, condizione
      ├─ Clicca annuncio interessante
      └─ Vede dettagli, foto, rating seller

4️⃣ BUYER PRENOTA
   └─ Modal conferma prenotazione
      ├─ Sistema crea booking (status: pending)
      ├─ Aggiorna listing (status: booked)
      ├─ Crea conversazione buyer-seller automaticamente
      └─ Annuncio sparisce dalla mappa

5️⃣ NEGOZIAZIONE VIA CHAT
   └─ Buyer e Seller si contattano
      ├─ Caricano cronologia messaggi
      ├─ Si accordano su data/ora ritiro
      └─ Aggiornano booking con scheduled_pickup_date

6️⃣ RITIRO DI PERSONA
   └─ Si incontrano e verificano prodotto
      ├─ Se OK, buyer marca come "Ritirato"
      └─ Sistema aggiorna booking (completed_at = NOW())

7️⃣ VALUTAZIONI RECIPROCHE
   └─ Entrambi scrivono review (1-5 stelle + commento)
      ├─ Sistema aggiorna user.rating_average
      └─ Reputazione costruita per transazioni future

8️⃣ FINE CICLO
   └─ Entrambi possono procedere a nuove transazioni
```

---

## 🗄️ Database Schema

ReuseIT utilizza 10 tabelle principali:

| Tabella | Descrizione |
|---------|------------|
| **users** | Profili utenti, coordinate GPS, rating |
| **categories** | Categorie di prodotto (Smartphone, Laptop, etc.) |
| **listings** | Annunci pubblicati con dettagli e localizzazione |
| **listing_photos** | Foto associate agli annunci |
| **bookings** | Prenotazioni e transazioni |
| **conversations** | Chat buyer-seller |
| **messages** | Messaggi singoli |
| **reviews** | Valutazioni post-transazione |
| **favorites** | Wishlist di annunci salvati |
| **reports** | Segnalazioni per moderazione |

### Caratteristiche del Database

- ✅ **Normalizzazione 3NF** - Elimina ridondanze, ottimizza consistency
- ✅ **Soft Delete** - Colonna `deleted_at` per GDPR e audit trail
- ✅ **Timestamps Automatici** - `created_at`, `updated_at` gestiti da DB
- ✅ **Indici Strategici** - GPS coordinates, status, user_id per query rapide
- ✅ **Foreign Keys** - Vincoli di integrità referenziale

Per schema SQL completo: vedi [docs/DB.md](./docs/DB.md)

---

## 🔌 API Endpoints

ReuseIT espone un'API REST JSON con 50+ endpoint:

### Autenticazione

| Metodo | Endpoint | Descrizione |
|--------|----------|-------------|
| POST | `/api/auth/register` | Registrazione nuovo utente |
| POST | `/api/auth/login` | Login con email/password |
| POST | `/api/auth/logout` | Logout |

### Profilo Utente

| Metodo | Endpoint | Descrizione |
|--------|----------|-------------|
| GET | `/api/users/{id}` | Profilo pubblico |
| PUT | `/api/users/{id}` | Modifica profilo |
| POST | `/api/users/{id}/avatar` | Upload foto profilo |
| GET | `/api/users/{id}/stats` | Statistiche annunci/vendite |
| GET | `/api/users/{id}/reviews` | Valutazioni ricevute |

### Annunci (Listings)

| Metodo | Endpoint | Descrizione |
|--------|----------|-------------|
| GET | `/api/listings` | Lista annunci con filtri |
| GET | `/api/listings/{id}` | Dettagli annuncio |
| POST | `/api/listings` | Pubblica annuncio |
| PUT | `/api/listings/{id}` | Modifica annuncio |
| DELETE | `/api/listings/{id}` | Soft delete annuncio |
| GET | `/api/listings/nearby` | Annunci entro N km |

### Prenotazioni (Bookings)

| Metodo | Endpoint | Descrizione |
|--------|----------|-------------|
| POST | `/api/bookings` | Crea prenotazione |
| GET | `/api/bookings/my` | Mie prenotazioni |
| PUT | `/api/bookings/{id}` | Aggiorna status |
| PUT | `/api/bookings/{id}/complete` | Completa ritiro |
| DELETE | `/api/bookings/{id}` | Cancella prenotazione |

### Chat e Messaggi

| Metodo | Endpoint | Descrizione |
|--------|----------|-------------|
| GET | `/api/conversations` | Mie conversazioni |
| GET | `/api/conversations/{id}/messages` | Messaggi conversazione |
| POST | `/api/messages` | Invia messaggio |
| PUT | `/api/messages/{id}/read` | Marca come letto |

### Valutazioni (Reviews)

| Metodo | Endpoint | Descrizione |
|--------|----------|-------------|
| POST | `/api/reviews` | Crea valutazione |
| GET | `/api/reviews/user/{userId}` | Valutazioni ricevute |

### Altro

| Metodo | Endpoint | Descrizione |
|--------|----------|-------------|
| GET | `/api/categories` | Lista categorie |
| POST | `/api/upload` | Upload foto |
| GET | `/api/image` | Serve foto |
| POST | `/api/favorites` | Aggiungi preferito |
| GET | `/api/favorites` | Miei preferiti |
| POST | `/api/reports` | Segnala annuncio/utente |

Per dettagli completi: vedi [docs/ENDPOINTS.md](./docs/ENDPOINTS.md)

---

## 📋 Formato Risposta API

### Success Response

```json
{
  "success": true,
  "data": {
    "id": 1,
    "title": "iPhone 12 Pro",
    "price": 650.00,
    "condition": "Ottimo",
    "status": "active"
  },
  "timestamp": "2026-03-30T10:30:00+00:00"
}
```

### Error Response

```json
{
  "success": false,
  "message": "Email già registrata",
  "errors": {
    "email": "Email già registrata"
  },
  "timestamp": "2026-03-30T10:30:00+00:00"
}
```

---

## 🔒 Sicurezza

ReuseIT implementa best practices di sicurezza:

- ✅ **SQL Injection Prevention** - PDO prepared statements su tutte le query
- ✅ **Password Hashing** - `password_hash()` e `password_verify()`
- ✅ **Session Management** - `session_start()` con Session wrapper
- ✅ **File Upload Validation** - MIME type checking, size limits, filename randomization
- ✅ **Coordinate Validation** - Range checking (lat: -90/90, lng: -180/180)
- ✅ **HTTPS Recommended** - Per production deployment

---

## 📊 Statistiche Progetto

| Metrica | Valore |
|---------|--------|
| **Tabelle DB** | 10 |
| **Endpoint API** | 50+ |
| **Controllers** | 9 |
| **Services** | 8 |
| **Repositories** | 10 |
| **Linguaggio** | PHP 7.4+ |

---

## 🧪 Test e Sviluppo

### Testare Endpoints con cURL

```bash
# Login
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"luca.rossi@email.com","password":"password"}'

# Lista annunci
curl http://localhost:8000/api/listings?category=1&limit=10

# Dettagli annuncio
curl http://localhost:8000/api/listings/1
```

### Struttura Test Data

Il file `ReuseIT.sql` include:
- 5 utenti con rating diversi
- 7 annunci in varie categorie
- 2 prenotazioni (pending e completed)
- 3 conversazioni con messaggi
- 3 valutazioni
- 4 preferiti

Perfetto per testare l'intero flusso!

---

## 🚧 Sviluppo Futuro

### Planned Features

- [ ] Google Maps API integration (geocoding, distance matrix)
- [ ] Email notifications (prenotazioni, nuovi messaggi)
- [ ] SMS alerts (opzionale)
- [ ] Push notifications browser
- [ ] Dashboard admin (moderazione, statistiche)
- [ ] Pagamenti integrati (Stripe/PayPal)
- [ ] Mobile app (React Native)
- [ ] Real-time notifications (WebSocket)
- [ ] Algoritmi di discovery (machine learning)
- [ ] Sistema di rating avanzato (badges, trust score)

---

## 📚 Documentazione

Documentazione completa disponibile in:

- **[docs/reuseit.md](./docs/reuseit.md)** - Vision, architettura, workflow
- **[docs/backend.md](./docs/backend.md)** - Specifiche tecniche backend
- **[docs/DB.md](./docs/DB.md)** - Schema database e query patterns
- **[docs/ENDPOINTS.md](./docs/ENDPOINTS.md)** - Documentazione API completa

---

## 🤝 Sviluppo e Contribute

### Come Contribuire

1. Fork il repository
2. Crea un branch per la tua feature (`git checkout -b feature/amazing-feature`)
3. Commit i tuoi cambiamenti (`git commit -m 'Add amazing feature'`)
4. Push al branch (`git push origin feature/amazing-feature`)
5. Apri una Pull Request

### Linee Guida di Codice

- **Naming**: Classes in PascalCase, methods/variables in camelCase
- **Structure**: Rispetta l'architettura layered (Controllers → Services → Repositories)
- **Error Handling**: Usa exceptions e Value Objects per validazione
- **Database**: Prepared statements SEMPRE, soft delete per entities principali
- **Testing**: Testa endpoint con cURL prima di committare

---

## 📄 Licenza

Questo progetto è licenziato sotto MIT License - vedi [LICENSE](LICENSE) per dettagli.

---

## 👨‍💻 Autore

Sviluppato come showcase project di architettura backend pulita in PHP vanilla.

---

## 📞 Contatti e Supporto

Per domande, segnalazioni o suggerimenti:

- **Issues**: Usa GitHub Issues per bug reports
- **Discussions**: Apri una discussion per domande generali
- **Email**: [contribuisci un email se disponibile]

---

**ReuseIT - Dai nuova vita alla tecnologia.**

*Creato 2026 | Made with ❤️ for sustainable electronics reuse*
