# ReuseIT - Marketplace per Elettronica Usata

## 1. VISION E SCOPO

**ReuseIT** è un marketplace web per la compravendita di dispositivi elettronici usati. Gli utenti possono:
- Pubblicare annunci di dispositivi che vogliono vendere
- Prenotare annunci che vogliono acquistare tramite ritiro di persona
- Visualizzare annunci su una mappa interattiva per trovare prodotti vicini geograficamente
- Comunicare con buyer/seller tramite chat per accordarsi sul ritiro
- Scambiarsi valutazioni post-transazione per costruire reputazione

### Valore Principale
ReuseIT risolve il problema del mercato dell'usato elettronico creando:
- Un ecosistema **decentralizzato** (ritiro fisico diretto, no spedizioni)
- Una **reputazione basata su valutazioni** (fiducia tra sconosciuti)
- Una **geolocalizzazione intuitiva** (trova oggetti vicini su mappa)
- Una **comunicazione diretta** (chat buyer-seller per accordarsi)

### Casi d'Uso Principali
1. **Seller**: Pubblica annuncio → riceve prenotazione → si accorda in chat → completa ritiro → riceve valutazione
2. **Buyer**: Naviga mappa → visualizza dettagli → prenota → contatta seller in chat → ritira → valuta

---

## 2. PANORAMICA TECNICA

### Stack Tecnologico

| Componente | Scelta | Motivo |
|-----------|--------|--------|
| **Linguaggio Backend** | PHP 7.4+ Plain (no framework) | Showcase project, controllo totale architettura |
| **Database** | MySQL + PDO | Prepared statements, protezione SQL injection |
| **Frontend** | HTML/CSS/JavaScript vanilla | Plain JS, fetch API, no dependencies |
| **Mappa** | Google Maps API | Geolocalizzazione, geocoding, visualizzazione |
| **Autenticazione** | PHP Sessions | State-full, nativa, semplice |
| **Storage Immagini** | Filesystem locale | Per showcase, facile gestione |

### Architettura Generale

L'applicazione segue un'architettura **layered** con separazione netta delle responsabilità:

```
┌──────────────────────────────────────────────────┐
│  Browser (HTML + CSS + JavaScript vanilla)       │
│  - Fetch API per comunicazione                   │
│  - Google Maps per visualizzazione               │
└──────────────────┬───────────────────────────────┘
                   │ REST API (JSON)
                   ↓
┌──────────────────────────────────────────────────┐
│  public/api.php (Front Controller)               │
│  - Entry point unico per tutte le API            │
│  - Autoload classi, routing, error handling      │
└──────────────────┬───────────────────────────────┘
                   │
                   ↓
┌──────────────────────────────────────────────────┐
│  Controllers (HTTP Layer)                        │
│  - Parse request, validazione input              │
│  - Chiama Services, restituisce JSON             │
└──────────────────┬───────────────────────────────┘
                   │
                   ↓
┌──────────────────────────────────────────────────┐
│  Services (Business Logic)                       │
│  - Orchestrazione repositories                   │
│  - Logica di dominio, validazioni                │
│  - Transazioni DB quando necessario              │
└──────────────────┬───────────────────────────────┘
                   │
                   ↓
┌──────────────────────────────────────────────────┐
│  Repositories (Data Access)                      │
│  - Query SQL con PDO prepared statements         │
│  - Hydration entity da risultati DB              │
│  - Operazioni CRUD                               │
└──────────────────┬───────────────────────────────┘
                   │
                   ↓
┌──────────────────────────────────────────────────┐
│  MySQL Database                                  │
│  - 10 tabelle normalized 3NF                     │
│  - Indici su coordinate GPS, status, user_id    │
└──────────────────────────────────────────────────┘
```

### Flusso Request/Response Tipico

```
1. Browser: fetch('/api/listings?category=1')
2. Apache rewrite (.htaccess): rerouta a public/api.php?path=listings
3. Front controller: session_start(), autoload, istanzia Router
4. Router: dispatch('listings', 'GET') → ListingController::getAll()
5. Controller: chiama ListingService::getActiveListings($filters)
6. Service: valida filtri, chiama ListingRepository::findActive()
7. Repository: query SQL con WHERE status='active', hydra risultati
8. Response: JSON { success: true, data: [...], timestamp: "..." }
9. Browser: riceve JSON, elabora con JavaScript
```

---

## 3. MODULI PRINCIPALI

### 3.1 Gestione Utenti e Autenticazione
**Responsabilità**: Registrazione, login, profili, avatar, statistiche, rating

**Entità**: User
**Services**: AuthService, UserService
**Controller**: AuthController, UserController

**Funzionalità**:
- Registrazione email/password
- Login con sessione
- Profilo pubblico (visualizzabile da chiunque)
- Modifica profilo (solo proprietario)
- Upload avatar
- Statistiche: annunci attivi, venduti, rating medio
- Valutazioni ricevute

### 3.2 Annunci (Listings)
**Responsabilità**: Pubblicazione, ricerca, geolocalizzazione, filtri

**Entità**: Listing, ListingPhoto, Category
**Services**: ListingService, ImageUploadService, GeoService
**Controller**: ListingController, ImageController

**Funzionalità**:
- Pubblica annuncio (CRUD)
- Upload foto multiplo
- Visualizza annunci attivi su mappa (latitude, longitude)
- Filtri: categoria, prezzo, condizione, distanza
- Ricerca per keyword
- Annunci vicini (query Haversine per distanza GPS)
- View count incrementale
- Stati: active → booked → completed / cancelled

### 3.3 Prenotazioni (Bookings)
**Responsabilità**: Workflow booking, ritiro, cancellazione

**Entità**: Booking
**Services**: BookingService
**Controller**: BookingController

**Funzionalità**:
- Prenota annuncio (crea booking, aggiorna listing status, crea conversazione)
- Visualizza prenotazioni (buyer/seller separate)
- Aggiorna data ritiro prevista
- Completa ritiro (marked completed_at)
- Cancella prenotazione
- Stati: pending → confirmed → completed / cancelled

### 3.4 Chat e Messaggi
**Responsabilità**: Comunicazione realtime buyer-seller, notifiche non lette

**Entità**: Conversation, Message
**Services**: ChatService
**Controller**: ChatController

**Funzionalità**:
- Crea conversazione (automatica al booking, o manuale "Contatta venditore")
- Invia messaggi
- Carica cronologia conversazione
- Marca messaggi come letti
- Conteggio non letti (unread_by_buyer, unread_by_seller)
- Ultimo messaggio con timestamp

### 3.5 Valutazioni (Reviews)
**Responsabilità**: Sistema di reputazione post-transazione

**Entità**: Review
**Services**: ReviewService
**Controller**: ReviewController

**Funzionalità**:
- Scrivi valutazione (disponibile solo post-ritiro completato)
- Rating 1-5 stelle
- Commento opzionale
- Calcolo rating medio utente (aggiornato dopo ogni review)
- Visualizza valutazioni ricevute da un utente
- Badge affidabilità basato su rating

### 3.6 Moduli Secondari
**Preferiti**: Salva annunci in wishlist (Favorite)
**Segnalazioni**: Report annunci/utenti (Report)

---

## 4. WORKFLOW DI SVILUPPO

### STEP 1: Backend

#### 4.1.1 Struttura Directory
```
src/
├── Core/                    (Foundation)
│   ├── Database.php        (Singleton PDO)
│   ├── Router.php          (Route dispatcher)
│   ├── Request.php         (HTTP input)
│   ├── Response.php        (HTTP output JSON)
│   └── Session.php         (Auth state)
│
├── Database/               (Data access)
│   └── Connection.php      (Config connessione)
│
├── Entities/               (Domain models)
│   ├── User.php
│   ├── Listing.php
│   ├── Booking.php
│   ├── Conversation.php
│   ├── Message.php
│   ├── Review.php
│   └── ... (altri)
│
├── ValueObjects/           (Validazione)
│   ├── Email.php
│   ├── Price.php
│   ├── Coordinates.php
│   └── ... (altri)
│
├── DTOs/                   (Data transfer)
│   ├── UserDTO.php
│   ├── ListingDTO.php
│   └── ... (altri)
│
├── Repositories/           (Query layer)
│   ├── UserRepository.php
│   ├── ListingRepository.php
│   ├── BookingRepository.php
│   └── ... (uno per entity)
│
├── Services/               (Business logic)
│   ├── AuthService.php
│   ├── UserService.php
│   ├── ListingService.php
│   ├── BookingService.php
│   ├── ChatService.php
│   ├── ReviewService.php
│   └── ... (uno per dominio)
│
├── Controllers/            (HTTP handlers)
│   ├── AuthController.php
│   ├── UserController.php
│   ├── ListingController.php
│   ├── BookingController.php
│   ├── ChatController.php
│   ├── ReviewController.php
│   └── ... (uno per dominio)
│
├── Middleware/
│   └── AuthMiddleware.php  (Protezione route)
│
└── Utils/
    └── Helpers.php         (Funzioni utility)

public/
├── index.php               (Homepage SPA)
├── api.php                 (Front controller API)
├── .htaccess               (Apache rewrite)
├── style.css
└── script.js
```

#### 4.1.2 Implementazione per Aree

**Core Layer** (MUST HAVE FIRST):
- ✅ Database.php - PDO singleton con query, execute, transactions
- ✅ Router.php - Pattern matching {id}, dispatch controller@action
- ✅ Request.php - getMethod, getJsonBody, getQuery, getFile
- ✅ Response.php - success/error con formato standard JSON
- ✅ Session.php - Wrapper $_SESSION, auth state

**Entities** (MUST HAVE):
- ✅ User, Listing, ListingPhoto, Category
- ✅ Booking, Conversation, Message
- ✅ Review, Favorite, Report

**Repositories** (MUST HAVE):
- Uno per entity principale
- Metodi: getById, create, update, delete, findActive, findNearby (per listing)
- Soft delete sempre (WHERE deleted_at IS NULL)

**Services** (MUST HAVE):
- AuthService: register, login, logout
- UserService: getProfile, updateProfile, uploadAvatar
- ListingService: create, update, getActiveListings, getNearby
- BookingService: createBooking, updateStatus, complete
- ChatService: getOrCreateConversation, sendMessage, getMessages
- ReviewService: createReview, calculateRating

**Controllers** (MUST HAVE):
- Tutti gli endpoint definiti in backend.md
- Try/catch per error handling
- Response::success/error

#### 4.1.3 Endpoints Core da Implementare
```
AUTH
POST   /api/auth/register       - Registrazione
POST   /api/auth/login          - Login
POST   /api/auth/logout         - Logout

USERS
GET    /api/users/{id}          - Profilo
PUT    /api/users/{id}          - Modifica profilo
POST   /api/users/{id}/avatar   - Upload avatar
GET    /api/users/{id}/stats    - Statistiche
GET    /api/users/{id}/reviews  - Valutazioni ricevute

LISTINGS
GET    /api/listings            - Lista con filtri
GET    /api/listings/{id}       - Dettagli
POST   /api/listings            - Pubblica
PUT    /api/listings/{id}       - Modifica
DELETE /api/listings/{id}       - Elimina
GET    /api/listings/nearby     - Annunci vicini

BOOKINGS
POST   /api/bookings            - Prenota
GET    /api/bookings/my         - Mie prenotazioni
PUT    /api/bookings/{id}       - Aggiorna status
PUT    /api/bookings/{id}/complete - Completa ritiro
DELETE /api/bookings/{id}       - Cancella

CONVERSATIONS & MESSAGES
GET    /api/conversations       - Mie chat
GET    /api/conversations/{id}/messages - Messaggi
POST   /api/messages            - Invia messaggio

REVIEWS
POST   /api/reviews             - Scrivi valutazione
GET    /api/reviews/user/{id}   - Valutazioni utente

UPLOAD & IMAGES
POST   /api/upload              - Upload foto
GET    /api/image               - Serve foto

CATEGORIES
GET    /api/categories          - Lista categorie
```

---

### STEP 2: Frontend

#### 4.2.1 Pagine Principali

**Index.php** - Single Page Application
- Routing frontend con history API
- Nav principale
- Area contenuti dinamica

**Pagine Principali**:
1. **Login/Registrazione** - Form auth
2. **Mappa** - Google Maps, marker annunci, filtri sidebar
3. **Dettagli Annuncio** - Carousel foto, info seller, pulsanti azione
4. **Profilo Utente** - Foto, bio, valutazioni, statistiche
5. **Pubblica Annuncio** - Form multi-step (categoria, dettagli, foto, mappa, revisione)
6. **Inbox Chat** - Lista conversazioni, chat detail
7. **Prenotazioni** - Tabs: In attesa, Confermate, Completate (buyer/seller)
8. **Preferiti** - Wishlist annunci salvati

#### 4.2.2 Funzionalità JavaScript
- Fetch API per comunicazione con backend
- Google Maps API integration
- Form validation
- Real-time UI updates
- Local storage per session
- Image carousel
- Modal dialogs
- Location picker

---

### STEP 3: Implementazioni API Esterne

#### 4.3.1 Google Maps API

**Funzionalità richieste**:
1. **Map Visualization** - Mostra mappa interattiva con marker
   - Endpoint: GET /api/listings/nearby con coordinates
   - Frontend: Carica mappa, plotta marker per ogni listing
   - Click marker: mostra card preview

2. **Geocoding** - Indirizzo fisico → Coordinate (lat/lng)
   - Quando user pubblica annuncio e inserisce indirizzo
   - Service: GeoService::getCoordinatesFromAddress()
   - Salva latitude, longitude nel listing

3. **Distance Calculation** - Distanza tra coordinate
   - Formula Haversine (oppure Google Distance Matrix API)
   - Repository: ListingRepository::findNearby()
   - Filtra annunci entro N km dalle coordinate user

#### 4.3.2 Integrazioni Opzionali (Future)
- **Pagamenti**: Stripe/PayPal (se necessario)
- **SMS/Email**: Notifiche transazioni, nuovi messaggi
- **Push Notifications**: Notifiche browser

---

## 5. FLUSSO UTENTE COMPLETO

### Ciclo Acquisto/Vendita Tipico

```
FASE 1: SETUP
└─ Utente si registra
   ├─ POST /api/auth/register
   ├─ Salva profilo con coordinate (se indirizzo fornito)
   └─ Login automatico

FASE 2: SELLER PUBBLICA ANNUNCIO
└─ Seller naviga "Pubblica Annuncio"
   ├─ Form multi-step: categoria, dettagli, foto, localizzazione
   ├─ Upload foto → POST /api/upload
   ├─ Seleziona indirizzo/coordinate sulla mappa
   ├─ POST /api/listings (crea listing + foto)
   └─ Annuncio live sulla mappa (status: active)

FASE 3: BUYER SCOPRE ANNUNCIO
└─ Buyer apre app
   ├─ Vede mappa con marker annunci attivi
   ├─ Filtra per categoria, prezzo, distanza
   ├─ Clicca annuncio interessante
   ├─ GET /api/listings/{id} (vede dettagli, foto, seller rating)
   └─ Valuta se prenotare

FASE 4: BUYER PRENOTA
└─ Buyer clicca "Prenota"
   ├─ Modal conferma (mostra summary)
   ├─ POST /api/bookings (crea booking, aggiorna listing status → booked)
   ├─ Sistema crea automaticamente conversazione (conversation.id)
   ├─ Redirect a chat
   └─ Annuncio sparisce da mappa

FASE 5: NEGOZIAZIONE CHAT
└─ Buyer e Seller contattano via chat
   ├─ GET /api/conversations/{id}/messages (carica cronologia)
   ├─ POST /api/messages (invia messaggio)
   ├─ Si accordano su data/ora ritiro
   ├─ PUT /api/bookings/{id} (aggiorna scheduled_pickup_date)
   └─ Messaggio "Data ritiro confermata"

FASE 6: RITIRO
└─ Si incontrano di persona
   ├─ Buyer verifica prodotto
   ├─ Se OK, Buyer segna come "Ritirato"
   ├─ PUT /api/bookings/{id}/complete (completed_at = NOW())
   ├─ UPDATE listings (status → completed)
   └─ Booking passa a "Completate"

FASE 7: VALUTAZIONI
└─ Entrambi (opzional):
   ├─ POST /api/reviews (rating 1-5, commento)
   ├─ Sistema aggiorna user.rating_average
   └─ Reputazione costruita per future transazioni

FASE 8: FINE CICLO
└─ Buyer vede valutazione ricevuta
└─ Seller vede valutazione ricevuta
└─ Entrambi possono procedere a nuove transazioni
```

---

## 6. DATABASE SCHEMA (RIEPILOGO)

Vedi **docs/DB.md** per schema completo e script MySQL.

**Tabelle principali**:
- `users` - Profili utenti
- `listings` - Annunci pubblicati
- `listing_photos` - Foto per annuncio
- `bookings` - Prenotazioni (booking + ritiro)
- `conversations` - Chat buyer-seller
- `messages` - Messaggi singoli
- `reviews` - Valutazioni post-transazione
- `categories` - Categorie annunci
- `favorites` - Annunci salvati
- `reports` - Segnalazioni (moderazione)

**Caratteristiche**:
- ✅ Normalized 3NF
- ✅ Soft delete con deleted_at
- ✅ Timestamps created_at/updated_at
- ✅ Indici su: status, seller_id, buyer_id, coordinates (lat/lng)
- ✅ Foreign keys con integrità referenziale

---

## 7. PATTERN E CONVENTIONS

### Pattern Architetturali
- **Layered Architecture** - Separazione responsabilità (Controllers → Services → Repositories)
- **Repository Pattern** - Astrazione data access
- **Value Objects** - Validazione incapsulata (Email, Price, Coordinates)
- **DTOs** - Trasferimento dati tra layer
- **Soft Delete** - Conformità GDPR, audit trail

### Naming Conventions
- **Classes**: PascalCase (UserRepository, AuthService)
- **Methods**: camelCase (getUserById, createListing)
- **Properties**: snake_case in DB, camelCase in PHP objects
- **Constants**: UPPER_SNAKE_CASE
- **Files**: Stesso nome della classe principale

### Error Handling
- Value Objects lanciano `\InvalidArgumentException` se input invalido
- Services catturano eccezioni e le propagano con messaggi descrittivi
- Controllers catturano e convertono a Response::error()
- Response standard: `{ success: bool, data/message: *, errors: object, timestamp: string }`

### Validation
- Lato server in Services (Value Objects per campi specifici, DTO::validate())
- Lato client in JavaScript prima di submit (UX migliore)

---

## 8. RIFERIMENTI TECNICI

Per dettagli implementativi completi:

- **Backend architettura, layer, endpoints**: [`docs/backend.md`](./docs/backend.md)
- **Database schema, query patterns, SQL**: [`docs/DB.md`](./docs/DB.md)

---

## 9. NOTE IMPORTANTI PER IL DEVELOPMENT

### Sicurezza
- ✅ SQL Injection: PDO prepared statements (ALWAYS)
- ✅ Password: password_hash() + verify
- ✅ Session: session_start(), Session class
- ✅ CSRF: Implementare token se necessario
- ✅ File Upload: Validare MIME type + size + randomizzare filename
- ✅ Coordinates: Validare range (lat: -90/90, lng: -180/180)

### Performance
- Indici su query pesanti (coordinates, status, user_id)
- Paginazione su GET con array (limit/offset)
- Lazy load foto solo in dettagli listing (non in lista)
- Join user-listing per evitare N+1 queries

### Database Transactions
- Usare per operazioni multi-entity:
  - Booking + Conversation (transazione atomica)
  - Review + updateUserRating (per evitare race condition)

### Frontend Considerations
- Cache API responses dove appropriato (localStorage per categorie)
- Offline fallback basico
- Loading states per operazioni lunghe
- Real-time UI updates dopo POST/PUT/DELETE

---

## 10. CHECKLIST IMPLEMENTAZIONE

### Backend (STEP 1)
- [ ] Database creato da ReuseIT.sql
- [ ] Core layer completato (Database, Router, Request, Response, Session)
- [ ] Entities create per tutte le tabelle
- [ ] Repositories create per tutte le entities
- [ ] Services completati
- [ ] Controllers completati
- [ ] Tutti gli endpoint testati (Postman/curl)
- [ ] Error handling uniforme

### Frontend (STEP 2)
- [ ] Pagina login/registrazione
- [ ] Pagina mappa con Google Maps
- [ ] Pagina dettagli listing
- [ ] Pagina pubblica annuncio (multi-step)
- [ ] Pagina chat con conversazioni
- [ ] Pagina prenotazioni (buyer/seller)
- [ ] Pagina profilo utente
- [ ] Pagina preferiti
- [ ] Integrazione fetch API
- [ ] Form validation
- [ ] Error handling UI

### API Esterne (STEP 3)
- [ ] Google Maps API key setup
- [ ] Map visualization su frontend
- [ ] Geocoding (indirizzo → coordinate)
- [ ] Distance calculation (nearby listings)
- [ ] Opzionale: Pagamenti, SMS, email

---

**Creato: 2026 | ReuseIT Marketplace**
**Documentazione Interna per Coding Agents**
