# API Endpoints

## Health Check
**GET** `/api/health`

Verifica stato del server.

```bash
curl http://localhost:8000/api/health
```

---

## Registrazione
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
    "street": "Via Roma 1",
    "city": "Milano",
    "province": "MI",
    "postal_code": "20100",
    "country": "Italy"
  }'
```

---

## Login
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

## Logout
**POST** `/api/auth/logout`

Termina sessione.

```bash
curl -X POST http://localhost:8000/api/auth/logout \
  -b cookies.txt
```

---

## Profilo Personale
**GET** `/api/auth/me`

Recupera profilo utente autenticato. **Richiede sessione attiva.**

```bash
curl http://localhost:8000/api/auth/me \
  -b cookies.txt
```

---

## Profilo Pubblico Utente
**GET** `/api/users/:id`

Visualizza profilo utente (pubblico).

```bash
curl http://localhost:8000/api/users/1
```

---

## Aggiorna Profilo
**PATCH** `/api/users/:id/profile`

Aggiorna il proprio profilo. **Richiede sessione attiva. Puoi modificare solo il tuo profilo.**

```bash
curl -X PATCH http://localhost:8000/api/users/1/profile \
  -H "Content-Type: application/json" \
  -b cookies.txt \
  -d '{
    "first_name": "Jane",
    "bio": "Mi piace condividere"
  }'
```

---

## Note di Test

- Usa `-c cookies.txt` per salvare i cookie da login
- Usa `-b cookies.txt` per inviare i cookie nelle richieste successive
- Gli endpoint `/api/auth/me` e `/api/users/:id/profile` richiedono sessione attiva
- Tutti gli ID negli URL sono numerici (1, 2, 3, ecc.)
