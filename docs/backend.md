# ReuseIT Backend - Specifiche Tecniche e Architettura

## 📋 Indice

1. [Stack Tecnologico](#stack-tecnologico)
2. [Architettura Generale](#architettura-generale)
3. [Struttura File e Folder](#struttura-file-e-folder)
4. [Flusso delle Richieste](#flusso-delle-richieste)
5. [Layer Core](#layer-core)
6. [Database Layer](#database-layer)
7. [Value Objects e DTOs](#value-objects-e-dtos)
8. [Entity Layer](#entity-layer)
9. [Services Layer](#services-layer)
10. [Controllers API](#controllers-api)
11. [Middleware e Autenticazione](#middleware-e-autenticazione)
12. [Upload Immagini](#upload-immagini)
13. [Endpoints API Completi](#endpoints-api-completi)
14. [Gestione Errori](#gestione-errori)

---

## Stack Tecnologico

| Componente | Scelta | Motivo |
|-----------|--------|--------|
| **Linguaggio** | PHP 7.4+ | Plain PHP, senza framework |
| **Database** | MySQL + PDO | Prepared statements sicure, protezione SQL injection |
| **Autenticazione** | PHP Sessions | State-full, semplice, nativa |
| **Storage Foto** | Filesystem locale | Showcase project, gestione facile |
| **Validazione** | Value Objects | Logica incapsulata nei VO, reusabile |
| **Front Controller** | public/api.php | Unico entry point per tutte le API |
| **Routing** | Custom Router | Semplice pattern matching con parametri |

---

## Architettura Generale

L'applicazione segue un'architettura **layered** con separazione delle responsabilità:

```
┌─────────────────────────────────────────┐
│     Browser (JavaScript + Fetch)        │
└────────────────┬────────────────────────┘
                 │
                 ↓
┌─────────────────────────────────────────┐
│  .htaccess (Apache Rewrite Rules)       │
│  Rerouta /api/* verso public/api.php    │
└────────────────┬────────────────────────┘
                 │
                 ↓
┌─────────────────────────────────────────┐
│  public/api.php (FRONT CONTROLLER)      │
│  - Autoloader classi                    │
│  - Routing dispatch                     │
│  - Error handling                       │
└────────────────┬────────────────────────┘
                 │
                 ↓
┌─────────────────────────────────────────┐
│  src/Controllers/ (HTTP Layer)          │
│  - Parse request                        │
│  - Chiama Services                      │
│  - Restituisce Response JSON            │
└────────────────┬────────────────────────┘
                 │
                 ↓
┌─────────────────────────────────────────┐
│  src/Services/ (Business Logic)         │
│  - Validazione                          │
│  - Orchestrazione repository            │
│  - Logica di dominio                    │
└────────────────┬────────────────────────┘
                 │
                 ↓
┌─────────────────────────────────────────┐
│  src/Repositories/ (Data Access)        │
│  - Query SQL con PDO                    │
│  - Hydration entity                     │
│  - Transaction management               │
└────────────────┬────────────────────────┘
                 │
                 ↓
┌─────────────────────────────────────────┐
│  MySQL Database                         │
│  - users, listings, bookings, ecc.      │
└─────────────────────────────────────────┘
```

---

## Struttura File e Folder

### Directory Root

```
ReuseIT/
├── public/                              (WEB ROOT - Accessible via browser)
│   ├── index.php                        (Homepage SPA)
│   ├── api.php                          (Front Controller API)
│   ├── .htaccess                        (Apache rewrite rules)
│   ├── style.css                        (Frontend styles)
│   └── script.js                        (Frontend JavaScript)
│
├── uploads/                             (Storage immagini - NOT web accessible)
│   ├── profile_pictures/                (Avatar utenti)
│   └── listing_photos/                  (Foto annunci)
│
├── src/                                 (Codice backend)
│   ├── Core/                            (Foundation layer)
│   ├── Database/                        (Database layer)
│   ├── Entities/                        (Domain models)
│   ├── ValueObjects/                    (Validazione e logica di valore)
│   ├── DTOs/                            (Data Transfer Objects)
│   ├── Repositories/                    (Data access layer)
│   ├── Services/                        (Business logic layer)
│   ├── Controllers/                     (HTTP request handlers)
│   ├── Middleware/                      (Request/response middleware)
│   └── Utils/                           (Helper functions)
│
├── config/                              (Configurazione)
│   ├── database.php                     (Connessione PDO)
│   ├── constants.php                    (Costanti applicative)
│   └── env.php                          (Variabili ambiente)
│
├── ReuseIT.sql                          (Schema database con dati test)
├── backend.md                           (Questo file)
├── ReuseIT.md                           (Documentazione app)
└── README.md                            (Setup e istruzioni)
```

---

## Flusso delle Richieste

### Ciclo Completo Request/Response

```
1. BROWSER INVIA RICHIESTA
   fetch('/api/listings?category=1')
   
2. APACHE .htaccess
   RewriteRule ^api/(.*)$ public/api.php?path=$1
   
3. public/api.php (FRONT CONTROLLER)
   - session_start()
   - Autoload classi src/
   - Istanzia Router
   - Chiama: router->dispatch('listings', 'GET')
   
4. Router.php
   - Matcha route: GET /listings
   - Trovato: ListingController@getAll
   
5. ListingController::getAll()
   - Estrae parametri query ($category)
   - Chiama: ListingService::getActiveListings($filters)
   - Restituisce: Response::success($listings)
   
6. ListingService::getActiveListings()
   - Valida filtri
   - Chiama: ListingRepository::findActive($filters)
   
7. ListingRepository::findActive()
   - Costruisce query SQL WHERE status='active' AND category_id=?
   - Esegue preparated statement PDO
   - Hydra risultati in Entity\Listing
   
8. Response::success()
   - Header: Content-Type: application/json
   - Body: { "success": true, "data": [...], "timestamp": "..." }
   
9. BROWSER RICEVE RISPOSTA
   response.json() → JavaScript elabora dati
```

---

## Layer Core

### Responsabilità e File

| File | Responsabilità |
|------|-----------------|
| **Database.php** | Singleton PDO, query execution, transaction management |
| **Router.php** | Pattern matching route, dispatch controller action |
| **Request.php** | Parser HTTP method, query params, JSON body, file upload |
| **Response.php** | Formatter JSON, HTTP status codes, error responses |
| **Session.php** | Wrapper sessioni PHP, authentication state |

### Database.php

Fornisce interfaccia PDO singleton:
- `query($sql, $params)` - SELECT restituisce array risultati
- `queryOne($sql, $params)` - SELECT restituisce riga singola
- `execute($sql, $params)` - INSERT/UPDATE/DELETE
- `lastInsertId()` - ID dell'ultimo insert
- `beginTransaction()`, `commit()`, `rollBack()` - Per operazioni atomiche

Configurazione da `config/database.php` con variabili d'ambiente:
- `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`
- PDO options: ERRMODE_EXCEPTION, FETCH_ASSOC, EMULATE_PREPARES=false

### Router.php

Mappa route HTTP ai controller:
- Registra rotte nel costruttore con metodi `get()`, `post()`, `put()`, `delete()`
- Pattern: `/users/{id}` → regex `/users/(\d+|[\w-]+)`
- Metodo `dispatch($path, $method)` esegue la rotta
- Estrae parametri {id} e li passa al controller

### Request.php

Utilità per leggere dati da richiesta HTTP:
- `getMethod()` - HTTP verb
- `getQuery()` / `getPost()` - Query/form data
- `getJsonBody()` - Parse JSON da input stream
- `getHeader()` - Header HTTP
- `getFile()` / `hasFile()` - Upload file

### Response.php

Utility per inviare risposte JSON:
- `success($data, $statusCode)` - 200/201 con success=true
- `error($message, $statusCode, $errors)` - 400/401/403/404/500 con success=false
- Format standard: `{ success, data/message, errors, timestamp }`

### Session.php

Wrapper attorno a `$_SESSION`:
- `set($key, $value)` - Salva in sessione
- `get($key, $default)` - Legge da sessione
- `setUserId($userId)` / `getId()` - Gestisce user_id autenticato
- `isAuthenticated()` - Controlla se user_id è presente
- `destroy()` - Logout

---

## Database Layer

### Repositories Pattern

Ogni Entity ha un corrispondente Repository:

| Entity | Repository | Metodi Principali |
|--------|-----------|---|
| **User** | UserRepository | getById, getByEmail, create, update, delete, updateRating |
| **Listing** | ListingRepository | getById, create, update, delete, findActive, findNearby, updateStatus, incrementViewCount |
| **ListingPhoto** | ListingPhotoRepository | getByListingId, create, delete |
| **Category** | CategoryRepository | getAll, getById |
| **Booking** | BookingRepository | getById, create, update, delete, findByBuyerId, findByMultipleId, getByListingId |
| **Conversation** | ConversationRepository | getById, create, getOrCreate, findByUserId, updateLastMessage |
| **Message** | MessageRepository | getById, create, getByConversationId, markAsRead |
| **Review** | ReviewRepository | getById, create, getByReviewedUserId, getByListingId |
| **Favorite** | FavoriteRepository | create, delete, getByUserId, isFavorited |
| **Report** | ReportRepository | create, getAll, update |

### Operazioni Comuni

Ogni repository implementa:
- **Create** - INSERT con prepared statement
- **Read** - SELECT by id, by field, findAll con paginazione
- **Update** - UPDATE con array di campi
- **Delete** - Soft delete con updated_at e deleted_at
- **Hydration** - Conversione row database → Entity object

### Query Patterns

- **Coordinate GPS** - Formula HAVERSINE per distanza tra punti
- **Soft Delete** - Sempre WHERE deleted_at IS NULL
- **Timestamps** - created_at e updated_at gestiti automaticamente dal database
- **Transactions** - Per operazioni multi-entity (es: booking + conversation)

---

## Value Objects e DTOs

### Value Objects (VO)

Incapsulano logica di validazione per campi specifici. Immutabili e auto-validanti:

| VO | Validazione | Uso |
|-----|------------|-----|
| **Email** | RFC 5322, filter_var() | Autenticazione, profili |
| **Price** | Numerico, >= 0 | Listing, booking |
| **Coordinates** | Lat [-90,90], Lng [-180,180] | Listing, user geolocalizzazione |
| **BookingStatus** | pending/confirmed/cancelled/completed | Enums booking |
| **ListingStatus** | active/booked/completed/cancelled | Enums listing |
| **Condition** | Ottimo/Buono/Accettabile/Scarso | Listing properties |
| **Rating** | 1-5 (int) | Reviews |
| **UserId** | BIGINT > 0 | Foreign keys |
| **ListingId** | BIGINT > 0 | Foreign keys |
| **Address** | street, city, province, postal_code, country | User/Listing location |

**Strategie di validazione:**
- Lanciano `\InvalidArgumentException` se il valore non è valido
- Conversione tipo (es: string → float per Price)
- Metodi getter per accesso ai valori
- Metodo `__toString()` per serializzazione

### DTOs (Data Transfer Objects)

Trasportano dati tra layer (Controller → Service → Repository):

| DTO | Campi | Uso |
|-----|-------|-----|
| **UserDTO** | email, first_name, last_name, password, phone, bio, address, coordinates | Registration, profile update |
| **ListingDTO** | seller_id, category_id, title, description, price, brand, model, condition, location, coordinates, photos | Create/update listing |
| **BookingDTO** | listing_id, buyer_id, seller_id, scheduled_pickup_date | Create booking |
| **MessageDTO** | conversation_id, sender_id, content | Send message |
| **ReviewDTO** | listing_id, reviewer_id, reviewed_user_id, rating, comment | Create review |
| **ConversationDTO** | listing_id, buyer_id, seller_id | Create conversation |
| **ListingPhotoDTO** | listing_id, photo_url, display_order | Add photo to listing |

**Responsabilità DTO:**
- Contengono solo dati, nessuna logica
- Metodo `validate()` che usa Value Objects
- Metodo `toArray()` per serializzazione
- Proprietà pubbliche per accesso facile

---

## Entity Layer

### Entities

Rappresentano le tabelle del database, uno-a-uno:

| Entity | Tabella | Proprietà Principali |
|--------|---------|-----|
| **User** | users | id, email, first_name, last_name, password_hash, phone, bio, profile_picture_url, address (street, city, province, postal_code, country), latitude, longitude, rating_average, rating_count, is_verified, created_at, updated_at, deleted_at |
| **Listing** | listings | id, seller_id, category_id, title, description, price, brand, model, year, condition, accessories (JSON), latitude, longitude, location_address, status, view_count, created_at, updated_at, deleted_at |
| **ListingPhoto** | listing_photos | id, listing_id, photo_url, display_order, created_at |
| **Category** | categories | id, name, description, icon_url, created_at |
| **Booking** | bookings | id, listing_id, buyer_id, seller_id, booking_status, booking_date, scheduled_pickup_date, completed_at, created_at, updated_at |
| **Conversation** | conversations | id, listing_id, buyer_id, seller_id, last_message_at, unread_by_seller, unread_by_buyer, created_at, updated_at |
| **Message** | messages | id, conversation_id, sender_id, content, is_read, read_at, created_at |
| **Review** | reviews | id, listing_id, reviewer_id, reviewed_user_id, rating, comment, created_at, updated_at |
| **Favorite** | favorites | id, user_id, listing_id, created_at |
| **Report** | reports | id, reporter_id, listing_id, reported_user_id, reason, description, status, created_at, updated_at |

**Responsabilità Entity:**
- Proprietà pubbliche che mappano i campi database
- Costruttore con parametri opzionali
- Metodo `toArray()` per serializzazione JSON
- Metodi helper per logica semplice (es: `isVerified()`, `getFullName()`)

---

## Services Layer

### Logica di Business

Ogni Service orchestrata i Repositories e implementa la logica di dominio:

| Service | Responsabilità | Metodi Principali |
|---------|-----------------|---|
| **AuthService** | Autenticazione, registrazione, login/logout, gestione sessioni | register, login, logout, getCurrentUser, isAuthenticated |
| **UserService** | Profilo utente, statistiche, foto profilo | getUserProfile, updateProfile, getUserStats, getReviews, uploadAvatar |
| **ListingService** | Annunci CRUD, geolocalizzazione, view count | createListing, updateListing, deleteListing, getActiveListings, getNearby, incrementViewCount, changeStatus |
| **BookingService** | Prenotazioni, ritiri, cancellazioni | createBooking, updateBooking, cancelBooking, completePickup, getMyBookings, getBookingDetails |
| **ChatService** | Conversazioni, messaggi, notifiche non lette | getOrCreateConversation, sendMessage, getConversationMessages, getMyConversations, markAsRead, markConversationRead |
| **ReviewService** | Valutazioni, calcolo rating medio | createReview, getReviewsByUser, updateUserRating, canLeaveReview |
| **FavoriteService** | Wishlist, preferiti | addFavorite, removeFavorite, getMyFavorites, isFavorited |
| **ImageUploadService** | Upload e validazione foto | uploadProfilePicture, uploadListingPhotos, deletePhoto |
| **GeoService** | Geolocalizzazione, distanze | getCoordinatesFromAddress (WIP con Google Maps), calculateDistance |
| **ReportService** | Segnalazioni e moderazione | createReport, getReports, updateReportStatus |

**Responsabilità Service:**
- Validazione input (tramite Value Objects e DTO::validate())
- Orchestrazione di più repositories (transazioni DB)
- Logica di business (regole dominio)
- Throw eccezioni descrittive
- Restituire Entity o array di Entity

---

## Controllers API

### HTTP Request Handlers

Ogni Controller gestisce un dominio di entità:

| Controller | Route Base | Responsabilità |
|-----------|-----------|---|
| **AuthController** | /api/auth | Registrazione, login, logout |
| **UserController** | /api/users | Profilo, statistiche, avatar |
| **ListingController** | /api/listings | CRUD annunci, ricerca, geolocalizzazione |
| **BookingController** | /api/bookings | CRUD prenotazioni, ritiri |
| **ChatController** | /api/conversations, /api/messages | Chat, messaggi, notifiche |
| **ReviewController** | /api/reviews | CRUD valutazioni |
| **FavoriteController** | /api/favorites | Wishlist |
| **ImageController** | /api/upload, /api/image | Upload foto, serve foto |
| **CategoryController** | /api/categories | Lista categorie |
| **ReportController** | /api/reports | Segnalazioni |

**Responsabilità Controller:**
- Parse richiesta (Request::getJsonBody(), Request::getQuery())
- Controlla autenticazione (Session::isAuthenticated())
- Chiama Service appropriato
- Cattura eccezioni e restituisce Response di errore
- Restituisce Response::success() o Response::error()

**Pattern di Error Handling nei Controller:**
```
try {
    // Valida input
    // Chiama Service
    // Restituisce Response::success()
} catch (Exception $e) {
    Response::badRequest($e->getMessage())
}
```

---

## Middleware e Autenticazione

### AuthMiddleware

Utility per proteggere route che richiedono autenticazione:
- `requireAuth()` - Lancia eccezione se user non loggato
- `requireRole($role)` - Controlla ruolo utente (futuro)

### Strategia di Autenticazione

- **Session-based** - `session_start()` in api.php
- **User ID in Session** - `$_SESSION['user_id']` dopo login
- **Check nei Controller** - `Session::isAuthenticated()` per protected routes

### Flow Autenticazione

```
1. POST /api/auth/login { email, password }
2. AuthService::login() verifica credenziali
3. Se valido: Session::setUserId($userId)
4. Response::success(user object)

5. Richiesta successiva: fetch('/api/listings', headers: authenticated)
6. Controller chiama: AuthMiddleware::requireAuth()
7. Se $SESSION['user_id'] non esiste: Response::unauthorized()
8. Se esiste: Continua elaborazione

9. POST /api/auth/logout
10. Session::destroy()
11. Response::success()
```

---

## Upload Immagini

### ImageUploadService

Gestisce upload e validazione:

| Metodo | Parametri | Output |
|--------|-----------|--------|
| **uploadProfilePicture** | $userId | Path relativo alla foto |
| **uploadListingPhotos** | $listingId | Array di path |

### Validazioni

- **MIME types** - image/jpeg, image/png, image/webp
- **Max size** - 5MB per file
- **Error handling** - Validazione con UPLOAD_ERR_OK
- **Naming** - uniqid() per filename univoci

### Directory Storage

```
uploads/
├── profile_pictures/
│   └── user_1/                  (Una cartella per utente)
│       └── 12345abc_photo.jpg
└── listing_photos/
    └── listing_1/               (Una cartella per annuncio)
        ├── 12345def_photo1.jpg
        └── 12346ghi_photo2.jpg
```

### Sicurezza

- Validazione MIME type
- Max file size check
- Filename randomizzato
- Non servire direttamente da uploads/
- Servire via `ImageController::getImage()` con path sanitization

---

## Endpoints API Completi

Documentazione di tutte le route con method HTTP, request/response:

### Autenticazione

| Metodo | Route | Descrizione | Auth | Request Body | Response |
|--------|-------|-----------|------|--------------|----------|
| POST | /api/auth/register | Registrazione nuovo utente | No | email, password, first_name, last_name, phone_number, address_city | User object |
| POST | /api/auth/login | Login con email/password | No | email, password | User object |
| POST | /api/auth/logout | Logout e distruggi sessione | Yes | - | { message: "Logout effettuato" } |

### Profilo Utente

| Metodo | Route | Descrizione | Auth | Query Params | Request Body | Response |
|--------|-------|-----------|------|-----|---|---|
| GET | /api/users/{id} | Ottieni profilo pubblico utente | No | - | - | User object |
| PUT | /api/users/{id} | Aggiorna profilo (solo il proprietario) | Yes | - | bio, address_*, phone_number, ecc. | User object aggiornato |
| POST | /api/users/{id}/avatar | Upload foto profilo | Yes | - | File multipart 'photo' | { path: "uploads/..." } |
| GET | /api/users/{id}/stats | Statistiche utente (annunci, vendite, rating) | No | - | - | { active_listings, sold_items, rating_avg, rating_count, total_reviews } |
| GET | /api/users/{id}/reviews | Valutazioni ricevute da utente | No | ?limit=10&offset=0 | - | Array di Review objects |

### Annunci (Listings)

| Metodo | Route | Descrizione | Auth | Query Params | Request Body | Response |
|--------|-------|-----------|------|-----|---|---|
| GET | /api/listings | Lista annunci attivi con filtri | No | ?category=1&price_min=100&price_max=500&condition=Ottimo&limit=20&offset=0 | - | Array di Listing objects (senza description per performance) |
| GET | /api/listings/{id} | Dettagli completi annuncio + foto | No | - | - | Listing object + array di ListingPhoto objects + seller info |
| POST | /api/listings | Pubblica nuovo annuncio | Yes | - | category_id, title, description, price, brand, model, year, condition, location_address, latitude, longitude, photos[] | Listing object |
| PUT | /api/listings/{id} | Aggiorna annuncio (solo proprietario) | Yes | - | title, description, price, condition, ecc. (senza foto) | Listing object aggiornato |
| DELETE | /api/listings/{id} | Soft delete annuncio | Yes | - | - | { message: "Annuncio eliminato" } |
| GET | /api/listings/nearby | Annunci entro N km da coordinate | No | ?latitude=45.4642&longitude=9.1900&distance=15 | - | Array di Listing objects ordinati per distanza |

### Prenotazioni (Bookings)

| Metodo | Route | Descrizione | Auth | Query Params | Request Body | Response |
|--------|-------|-----------|------|-----|---|---|
| POST | /api/bookings | Crea prenotazione per annuncio | Yes | - | listing_id | Booking object (status: pending) |
| GET | /api/bookings/my | Mie prenotazioni (buyer o seller) | Yes | ?role=buyer (oppure 'seller') | - | Array di Booking objects con listing e user info |
| PUT | /api/bookings/{id} | Aggiorna booking (status, scheduled_pickup_date) | Yes | - | booking_status, scheduled_pickup_date | Booking object aggiornato |
| PUT | /api/bookings/{id}/complete | Marca booking come completato (ritiro avvenuto) | Yes | - | - | Booking object con completed_at = NOW() |
| DELETE | /api/bookings/{id} | Cancella prenotazione | Yes | - | - | { message: "Prenotazione cancellata" } |

### Chat e Messaggi

| Metodo | Route | Descrizione | Auth | Query Params | Request Body | Response |
|--------|-------|-----------|------|-----|---|---|
| GET | /api/conversations | Tutte le mie conversazioni | Yes | ?limit=20&offset=0 | - | Array di Conversation objects con ultimo messaggio |
| GET | /api/conversations/{id}/messages | Messaggi di una conversazione | Yes | ?limit=50&offset=0 | - | Array di Message objects (crescente per created_at) |
| POST | /api/messages | Invia messaggio in conversazione | Yes | - | conversation_id, content | Message object |
| PUT | /api/messages/{id}/read | Marca messaggio come letto | Yes | - | - | { is_read: true } |

### Valutazioni (Reviews)

| Metodo | Route | Descrizione | Auth | Request Body | Response |
|--------|-------|-----------|------|---|---|
| POST | /api/reviews | Crea valutazione post-ritiro | Yes | listing_id, reviewed_user_id, rating (1-5), comment | Review object |
| GET | /api/reviews/user/{userId} | Valutazioni ricevute da utente | No | - | Array di Review objects |

### Preferiti (Favorites)

| Metodo | Route | Descrizione | Auth | Request Body | Response |
|--------|-------|-----------|------|---|---|
| POST | /api/favorites | Aggiungi annuncio ai preferiti | Yes | listing_id | { message: "Aggiunto ai preferiti" } |
| GET | /api/favorites | Miei annunci preferiti | Yes | - | Array di Listing objects |
| DELETE | /api/favorites/{listingId} | Rimuovi dai preferiti | Yes | - | { message: "Rimosso dai preferiti" } |

### Upload Immagini

| Metodo | Route | Descrizione | Auth | Query Params | Request Body | Response |
|--------|-------|-----------|------|-----|---|---|
| POST | /api/upload | Upload foto | Yes | ?type=profile&id=1 (oppure type=listing&id=1) | FormData file 'photo' o 'photos[]' | { path: "uploads/..." } oppure { paths: [...] } |
| GET | /api/image | Serve immagine dal filesystem | No | ?path=uploads/profile_pictures/... | - | Binary image data (Content-Type: image/jpeg) |

### Categorie

| Metodo | Route | Descrizione | Auth | Response |
|--------|-------|-----------|------|---|
| GET | /api/categories | Lista tutte le categorie | No | Array di Category objects |

### Segnalazioni (Reports)

| Metodo | Route | Descrizione | Auth | Request Body | Response |
|--------|-------|-----------|------|---|---|
| POST | /api/reports | Segnala annuncio o utente | Yes | listing_id (opzionale), reported_user_id (opzionale), reason, description | Report object |

---

## Gestione Errori

### HTTP Status Codes

| Codice | Scenario | Messaggio Esempio |
|--------|----------|---|
| **200 OK** | Request successful | Operazione completata |
| **201 Created** | Risorsa creata | POST /listings, POST /bookings |
| **400 Bad Request** | Validazione fallita, input malformato | Email già registrata, Prezzo invalido |
| **401 Unauthorized** | Utente non autenticato | Devi essere loggato |
| **403 Forbidden** | Utente non autorizzato per questa azione | Non puoi modificare annuncio altrui |
| **404 Not Found** | Risorsa non trovata | Utente/annuncio non esiste |
| **500 Internal Server Error** | Errore server generico | Database error, exception |

### Formato Risposta di Errore

**Success Response:**
```json
{
  "success": true,
  "data": { ... },
  "timestamp": "2024-01-15T10:30:00+00:00"
}
```

**Error Response:**
```json
{
  "success": false,
  "message": "Email già registrata",
  "errors": {
    "email": "Email già registrata"
  },
  "timestamp": "2024-01-15T10:30:00+00:00"
}
```

### Strategie di Error Handling

1. **Validazione Input** - Value Objects lanciano `\InvalidArgumentException`
2. **Catch in Controller** - Try/catch blocchi trasformano eccezioni in Response
3. **Logging** - Errori server registrati (implementazione futura)
4. **Messaggio User-Friendly** - Messaggi chiari, non tecnici

---

## Flusso Dati Completo: Esempio Prenotazione Annuncio

Ecco come i dati fluiscono attraverso i layer per una prenotazione:

```
1. BROWSER
   POST /api/bookings
   { "listing_id": 5 }

2. .htaccess → public/api.php
   Istanzia Router, dispatch('bookings', 'POST')

3. BookingController::create()
   - Session::isAuthenticated() → buyer_id
   - Request::getJsonBody() → { "listing_id": 5 }
   - Istanzia BookingDTO({ listing_id: 5, buyer_id: 123 })
   - Chiama BookingService::createBooking(dto)

4. BookingService::createBooking()
   - BookingRepository::getByListingId(5) → controlla non prenotato
   - ListingRepository::getById(5) → seller_id
   - Database::beginTransaction()
   - BookingRepository::create({ listing_id, buyer_id, seller_id, status: 'pending' })
   - ListingRepository::updateStatus(5, 'booked')
   - ConversationRepository::getOrCreate(listing_id, buyer_id, seller_id)
   - Database::commit()
   - Restituisce Booking entity

5. Response::created(booking)
   Header: HTTP 201
   Body: { "success": true, "data": { id, listing_id, buyer_id, status, ... } }

6. BROWSER
   Riceve risposta JSON
   Reindirizza a chat oppure mostra conferma
```

---

## Note Implementative Importanti

### Soft Delete
- Tutte le entity principali (User, Listing, Booking) hanno `deleted_at`
- Query: sempre `WHERE deleted_at IS NULL`
- Per GDPR compliance e audit trail

### Timestamps Automatici
- `created_at` - SET DEFAULT CURRENT_TIMESTAMP in DB
- `updated_at` - SET DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP in DB
- Repository non tocca questi campi in INSERT/UPDATE

### Transactions
- Usare per operazioni multi-entity:
  - Booking + Conversation (insieme nella stessa transazione)
  - Review + updateUserRating (transazione per evitare race condition)

### Paginazione
- Implementare limit/offset nei GET con array di risultati
- Default: limit=20, offset=0
- Documentare sempre su endpoint elenco

### Query Ottimizzazione
- Indici su: status, seller_id, buyer_id, created_at, latitude+longitude
- Join efficiente user-listing per evitare N+1 queries
- Lazy load foto solo in endpoint dettagli (GET /listings/{id})

---

**Questa guida contiene tutte le specifiche tecniche per implementare il backend. Procedi layer per layer seguendo la struttura definita.**
