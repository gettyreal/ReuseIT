# API Endpoints ReuseIT

Documentazione aggiornata agli endpoint realmente registrati nel router (`src/Router.php`).

## Regole generali

- Base URL locale: `http://localhost:8000`
- Formato risposta standard:
  - successo: `{ "success": true, "data": ... }`
  - errore: `{ "success": false, "error": "..." }`
  - validazione: `{ "success": false, "errors": [{"field":"...","message":"..."}] }`
- Autenticazione: sessione PHP (cookie). Per endpoint protetti usa `-b cookies.txt` e per login usa `-c cookies.txt`.

## Panoramica endpoint

| Metodo | Endpoint | Auth | Descrizione |
|---|---|---|---|
| GET | `/api/health` | No | Health check server |
| POST | `/api/auth/register` | No | Registrazione utente |
| POST | `/api/auth/login` | No | Login utente |
| POST | `/api/auth/logout` | No | Logout sessione corrente |
| GET | `/api/auth/me` | Si | Profilo utente autenticato |
| GET | `/api/users/:id` | No | Profilo pubblico utente |
| PATCH | `/api/users/:id/profile` | Si | Aggiorna proprio profilo |
| GET | `/api/listings` | No | Lista annunci con filtri base |
| GET | `/api/listings/search` | No* | Ricerca con distanza e filtri |
| GET | `/api/listings/filter-options` | No | Opzioni filtri per UI |
| GET | `/api/listings/:id` | No | Dettaglio annuncio |
| POST | `/api/listings` | Si | Crea annuncio |
| PATCH | `/api/listings/:id` | Si | Aggiorna annuncio (owner) |
| DELETE | `/api/listings/:id` | Si | Elimina annuncio (owner) |
| POST | `/api/listings/:id/photos` | Si | Upload foto annuncio (owner) |
| POST | `/api/users/:id/avatar` | Si | Upload avatar utente |
| GET | `/api/conversations` | Si | Lista conversazioni utente |
| GET | `/api/conversations/:id/messages` | Si | Storico messaggi conversazione |
| GET | `/api/conversations/:id/messages/new` | Si | Nuovi messaggi da timestamp |
| POST | `/api/conversations/:id/messages` | Si | Invia messaggio |
| PATCH | `/api/conversations/:id/mark-read` | Si | Segna conversazione come letta |
| PATCH | `/api/messages/:id/mark-read` | Si | Segna messaggio come letto |

`*` `/api/listings/search` e pubblico, ma se non passi `lat` e `lng` richiede utente autenticato (usa la posizione profilo).

---

## Health

### GET `/api/health`

Controllo stato server (non richiede DB).

```bash
curl http://localhost:8000/api/health
```

---

## Autenticazione

### POST `/api/auth/register`

Registra un nuovo utente.

Body JSON richiesto:
- `email`
- `password` (min 8)
- `first_name`
- `last_name`
- `address` oggetto con: `street`, `city`, `province`, `postal_code`, `country`

Opzionale:
- `coordinates` con `lat`, `lng`

```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "password123",
    "first_name": "Mario",
    "last_name": "Rossi",
    "address": {
      "street": "Via Roma 1",
      "city": "Milano",
      "province": "MI",
      "postal_code": "20100",
      "country": "Italy"
    }
  }'
```

### POST `/api/auth/login`

Login utente e creazione sessione.

Body JSON:
- `email`
- `password`

```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -c cookies.txt \
  -d '{"email":"user@example.com","password":"password123"}'
```

### POST `/api/auth/logout`

Logout sessione corrente.

```bash
curl -X POST http://localhost:8000/api/auth/logout -b cookies.txt
```

### GET `/api/auth/me` (protetto)

Ritorna il profilo dell'utente autenticato.

```bash
curl http://localhost:8000/api/auth/me -b cookies.txt
```

---

## Utenti

### GET `/api/users/:id`

Profilo pubblico utente.

```bash
curl http://localhost:8000/api/users/1
```

### PATCH `/api/users/:id/profile` (protetto)

Aggiorna profilo; solo il proprio profilo.

Campi aggiornabili (tutti opzionali):
- `first_name`, `last_name`, `bio`
- `address_street`, `address_city`, `address_province`, `address_postal_code`, `address_country`

```bash
curl -X PATCH http://localhost:8000/api/users/1/profile \
  -H "Content-Type: application/json" \
  -b cookies.txt \
  -d '{"bio":"Profilo aggiornato"}'
```

### POST `/api/users/:id/avatar` (protetto)

Upload avatar; solo il proprio avatar.

Body multipart/form-data:
- `avatar` file immagine

```bash
curl -X POST http://localhost:8000/api/users/1/avatar \
  -b cookies.txt \
  -F "avatar=@/path/to/avatar.jpg"
```

---

## Listings

### GET `/api/listings`

Lista annunci con filtri base e paginazione.

Query params opzionali:
- `limit` (default 20, max 100)
- `offset` (default 0)
- `category_id`
- `status`
- `price_min`
- `price_max`

```bash
curl "http://localhost:8000/api/listings?limit=20&offset=0"
```

### GET `/api/listings/:id`

Dettaglio annuncio.

```bash
curl http://localhost:8000/api/listings/42
```

### POST `/api/listings` (protetto)

Crea annuncio.

Body JSON tipico:
- `title`, `description`, `category_id`, `price`, `condition`, `address`
- opzionali: `brand`, `model`, `year`, `accessories`

```bash
curl -X POST http://localhost:8000/api/listings \
  -H "Content-Type: application/json" \
  -b cookies.txt \
  -d '{
    "title":"iPhone 12 Pro",
    "description":"Ottimo stato",
    "category_id":1,
    "price":650,
    "condition":"Excellent",
    "address": {
      "street":"Via Roma 10",
      "city":"Milano",
      "province":"MI",
      "postal_code":"20100",
      "country":"Italy"
    }
  }'
```

### PATCH `/api/listings/:id` (protetto)

Aggiorna annuncio; solo owner.

```bash
curl -X PATCH http://localhost:8000/api/listings/42 \
  -H "Content-Type: application/json" \
  -b cookies.txt \
  -d '{"price":599.99}'
```

### DELETE `/api/listings/:id` (protetto)

Soft delete annuncio; solo owner.

```bash
curl -X DELETE http://localhost:8000/api/listings/42 -b cookies.txt
```

### POST `/api/listings/:id/photos` (protetto)

Upload foto annuncio; solo owner. Limite max 10 foto per annuncio.

Body multipart/form-data:
- `photos` (puoi passare piu file)

```bash
curl -X POST http://localhost:8000/api/listings/42/photos \
  -b cookies.txt \
  -F "photos=@/path/to/photo1.jpg" \
  -F "photos=@/path/to/photo2.jpg"
```

---

## Ricerca listings

### GET `/api/listings/search`

Ricerca avanzata con distanza.

Query params opzionali:
- posizione: `lat`, `lng`
- raggio: `radius` (0-50000 metri, default 10000)
- filtri: `keyword`, `category_id`, `condition`, `price_min`, `price_max`
- paginazione: `limit` (max 100), `offset`

Nota comportamento auth:
- se passi `lat` + `lng` non serve autenticazione
- se non passi `lat`/`lng`, viene usata la posizione utente autenticato
- senza posizione e senza login ritorna `401`

```bash
curl "http://localhost:8000/api/listings/search?lat=45.46&lng=9.18&radius=10000&keyword=iphone"
```

### GET `/api/listings/filter-options`

Valori disponibili per i filtri UI (categorie, condizioni, range prezzi).

```bash
curl http://localhost:8000/api/listings/filter-options
```

---

## Chat

Tutti gli endpoint chat sono protetti (richiedono sessione valida).

### GET `/api/conversations`

Lista conversazioni utente.

Query params opzionali:
- `limit` (1-50, default 20)
- `offset` (default 0)

```bash
curl "http://localhost:8000/api/conversations?limit=20&offset=0" -b cookies.txt
```

### GET `/api/conversations/:id/messages`

Storico messaggi conversazione.

Query params opzionali:
- `limit` (1-50, default 20)
- `offset` (default 0)

Header risposta: `X-Poll-Interval: 3000`

```bash
curl "http://localhost:8000/api/conversations/1/messages?limit=20&offset=0" -b cookies.txt
```

### GET `/api/conversations/:id/messages/new`

Delta messaggi da timestamp (`since` obbligatorio).

Query params richiesti:
- `since` (formato data valido: `Y-m-d H:i:s` o ISO 8601)

Header risposta: `X-Poll-Interval: 3000`

```bash
curl "http://localhost:8000/api/conversations/1/messages/new?since=2026-03-30T10:00:00Z" -b cookies.txt
```

### POST `/api/conversations/:id/messages`

Invia messaggio in conversazione.

Body JSON:
- `content` (string, obbligatorio)

```bash
curl -X POST http://localhost:8000/api/conversations/1/messages \
  -H "Content-Type: application/json" \
  -b cookies.txt \
  -d '{"content":"Ciao! E ancora disponibile?"}'
```

### PATCH `/api/conversations/:id/mark-read`

Segna come letta una conversazione per l'utente autenticato.

```bash
curl -X PATCH http://localhost:8000/api/conversations/1/mark-read -b cookies.txt
```

### PATCH `/api/messages/:id/mark-read`

Segna come letto un singolo messaggio.

```bash
curl -X PATCH http://localhost:8000/api/messages/10/mark-read -b cookies.txt
```

---

## Errori HTTP comuni

- `400` parametri invalidi / validazione fallita
- `401` non autenticato
- `403` autenticato ma non autorizzato
- `404` risorsa non trovata
- `409` conflitto (es. limiti upload foto)
- `422` errori validazione semantica
- `429` troppi tentativi login
- `500` errore server
