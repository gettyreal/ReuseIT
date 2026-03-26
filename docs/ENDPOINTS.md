# API Endpoints

## Health Check
**GET** `/api/health`

Verifica stato del server.

```bash
curl http://localhost:8000/api/health
```

---

## Autenticazione & Utenti

### Registrazione
**POST** `/api/auth/register`

Crea nuovo utente.

```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "password123",
    "first_name": "John",
    "last_name": "Doe",
    "address": {
      "street": "Via Roma 1",
      "city": "Milano",
      "province": "MI",
      "postal_code": "20100",
      "country": "Italy"
    },
    "coordinates": {
      "lat": 45.4654,
      "lng": 9.1859
    }
  }'
```

**Note**: 
- `coordinates` è opzionale. Se non fornito, il sistema calcolerà automaticamente le coordinate tramite Google API
- Per testare ora senza Google API configurato, fornisci `coordinates` manualmente

---

### Login
**POST** `/api/auth/login`

Autentica utente (crea sessione).

```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -c cookies.txt \
  -d '{
    "email": "user@example.com",
    "password": "password123"
  }'
```

---

### Logout
**POST** `/api/auth/logout`

Termina sessione. **Richiede sessione attiva.**

```bash
curl -X POST http://localhost:8000/api/auth/logout \
  -b cookies.txt
```

---

### Profilo Personale
**GET** `/api/auth/me`

Recupera profilo utente autenticato. **Richiede sessione attiva.**

```bash
curl http://localhost:8000/api/auth/me \
  -b cookies.txt
```

---

### Profilo Pubblico Utente
**GET** `/api/users/:id`

Visualizza profilo pubblico di un utente.

**Parametri URL:**
- `:id` (required) — ID utente

```bash
curl http://localhost:8000/api/users/1
```

---

### Aggiorna Profilo Personale
**PATCH** `/api/users/:id/profile`

Aggiorna il proprio profilo. **Richiede sessione attiva. Puoi modificare solo il tuo profilo.**

**Parametri URL:**
- `:id` (required) — ID utente

**Parametri Body (tutti opzionali):**
- `first_name` — Nome (max 100 caratteri)
- `last_name` — Cognome (max 100 caratteri)
- `bio` — Biografia (max 255 caratteri)
- `address_street` — Via
- `address_city` — Città
- `address_province` — Provincia
- `address_postal_code` — CAP
- `address_country` — Paese

```bash
curl -X PATCH http://localhost:8000/api/users/1/profile \
  -H "Content-Type: application/json" \
  -b cookies.txt \
  -d '{
    "first_name": "Jane",
    "last_name": "Rossi",
    "bio": "Mi piace condividere",
    "address_street": "Via Verdi 10",
    "address_city": "Roma",
    "address_province": "RM",
    "address_postal_code": "00100",
    "address_country": "Italy"
  }'
```

---

### Carica Avatar Utente
**POST** `/api/users/:id/avatar`

Carica immagine avatar per l'utente. **Richiede sessione attiva. Puoi caricare solo il tuo avatar.**

**Parametri URL:**
- `:id` (required) — ID utente

**Parametri Body (multipart/form-data):**
- `avatar` (required) — File immagine (JPEG/PNG/WebP, max 5MB)

```bash
curl -X POST http://localhost:8000/api/users/1/avatar \
  -b cookies.txt \
  -F "avatar=@/path/to/avatar.jpg"
```

---

## Annunci (Listings)

### Crea Annuncio
**POST** `/api/listings`

Crea nuovo annuncio. **Richiede sessione attiva.**

**Parametri Body:**
- `title` (required) — Titolo annuncio (max 255 caratteri)
- `description` (required) — Descrizione (max 5000 caratteri)
- `category_id` (required) — ID categoria
- `price` (required) — Prezzo in euro (es: 650.00)
- `condition` (required) — Condizione (Excellent, Good, Fair, Poor)
- `address` (required) — Indirizzo come oggetto:
  - `street` — Via
  - `city` — Città
  - `province` — Provincia
  - `postal_code` — CAP
  - `country` — Paese
- `brand` (optional) — Marca
- `model` (optional) — Modello
- `year` (optional) — Anno di produzione
- `accessories` (optional) — Array di accessori inclusi (es: ["charger", "box"])

```bash
curl -X POST http://localhost:8000/api/listings \
  -H "Content-Type: application/json" \
  -b cookies.txt \
  -d '{
    "title": "iPhone 12 Pro",
    "description": "Perfetto stato, batteria al 95%",
    "category_id": 1,
    "price": 650.00,
    "condition": "Excellent",
    "address": {
      "street": "Via Roma 123",
      "city": "Milano",
      "province": "MI",
      "postal_code": "20100",
      "country": "Italy"
    },
    "brand": "Apple",
    "model": "iPhone 12 Pro",
    "year": 2021,
    "accessories": ["charger", "box"]
  }'
```

---

### Lista Annunci
**GET** `/api/listings`

Elenca tutti gli annunci attivi con filtri e paginazione.

**Parametri Query:**
- `limit` (optional, default 20, max 100) — Numero annunci per pagina
- `offset` (optional, default 0) — Offset paginazione
- `category_id` (optional) — Filtra per ID categoria
- `status` (optional) — Filtra per stato
- `price_min` (optional) — Prezzo minimo
- `price_max` (optional) — Prezzo massimo

```bash
curl "http://localhost:8000/api/listings?limit=20&offset=0&category_id=1&price_min=100&price_max=1000"
```

---

### Dettagli Annuncio
**GET** `/api/listings/:id`

Visualizza dettagli completi di un annuncio.

**Parametri URL:**
- `:id` (required) — ID annuncio

```bash
curl http://localhost:8000/api/listings/42
```

---

### Aggiorna Annuncio
**PATCH** `/api/listings/:id`

Aggiorna un annuncio. **Richiede sessione attiva. Solo il proprietario può modificare.**

**Parametri URL:**
- `:id` (required) — ID annuncio

**Parametri Body (tutti opzionali):**
- `title` — Titolo
- `description` — Descrizione
- `category_id` — ID categoria
- `price` — Prezzo
- `condition` — Condizione
- `address` — Indirizzo (oggetto con street, city, province, postal_code, country)
- `brand` — Marca
- `model` — Modello
- `year` — Anno
- `accessories` — Array di accessori

```bash
curl -X PATCH http://localhost:8000/api/listings/42 \
  -H "Content-Type: application/json" \
  -b cookies.txt \
  -d '{
    "title": "iPhone 12 Pro (RIDOTTO)",
    "price": 600.00,
    "condition": "Good"
  }'
```

---

### Elimina Annuncio
**DELETE** `/api/listings/:id`

Elimina (soft delete) un annuncio. **Richiede sessione attiva. Solo il proprietario può eliminare.**

**Parametri URL:**
- `:id` (required) — ID annuncio

```bash
curl -X DELETE http://localhost:8000/api/listings/42 \
  -b cookies.txt
```

---

### Carica Foto per Annuncio
**POST** `/api/listings/:id/photos`

Carica foto per un annuncio (max 10 foto). **Richiede sessione attiva. Solo il proprietario può caricare.**

**Parametri URL:**
- `:id` (required) — ID annuncio

**Parametri Body (multipart/form-data):**
- `photos` (required) — Array di file immagini (JPEG/PNG/WebP, max 5MB ciascuna)

```bash
curl -X POST http://localhost:8000/api/listings/42/photos \
  -b cookies.txt \
  -F "photos=@/path/to/photo1.jpg" \
  -F "photos=@/path/to/photo2.jpg" \
  -F "photos=@/path/to/photo3.jpg"
```

---

## Ricerca & Scoperta

### Ricerca Annunci
**GET** `/api/listings/search`

Ricerca avanzata di annunci con filtri multi-criteri.

**Parametri Query:**
- `keyword` (optional) — Ricerca per titolo/descrizione
- `category_id` (optional) — Filtra per categoria
- `condition` (optional) — Filtra per condizione (Excellent, Good, Fair, Poor)
- `price_min` (optional) — Prezzo minimo
- `price_max` (optional) — Prezzo massimo
- `limit` (optional, default 20, max 100) — Numero risultati
- `offset` (optional, default 0) — Offset paginazione

```bash
curl "http://localhost:8000/api/listings/search?keyword=iPhone&condition=Excellent&price_min=500&price_max=800&limit=20&offset=0"
```

---

### Opzioni Filtri
**GET** `/api/listings/filter-options`

Recupera valori disponibili per i filtri della UI (categorie, condizioni, range prezzi).

**Parametri Query:** Nessuno

```bash
curl http://localhost:8000/api/listings/filter-options
```

---
