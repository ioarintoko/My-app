# Instalasi & Operasi — Laravel Movie API + JWT Auth

## A. Instalasi

> `composer install` dan fix permission (`chown`/`chmod` storage & bootstrap/cache) **sudah otomatis** dijalankan lewat `Dockerfile` dan `docker-entrypoint.sh` setiap container start — tidak perlu dijalankan manual.

### 1. Jalankan container

```bash
docker compose up -d
```

Ini otomatis: install dependency composer (build time), fix permission storage/cache (tiap start), lalu jalankan `php-fpm`.

### 2. Generate app key (sekali saja, kalau `.env` belum punya `APP_KEY`)

```bash
docker compose exec app php artisan key:generate
```

### 3. Install JWT Auth (sekali saja di setup awal)

```bash
docker compose exec app composer require tymon/jwt-auth
docker compose exec app php artisan vendor:publish --provider="Tymon\JWTAuth\Providers\LaravelServiceProvider"
docker compose exec app php artisan jwt:secret
```

### 4. Jalankan migration

```bash
docker compose exec app php artisan migrate
```

### 5. Clear cache (hanya kalau ada perubahan config/route setelah setup awal)

```bash
docker compose exec app php artisan route:clear
docker compose exec app php artisan config:clear
```

Aplikasi siap diakses di `http://localhost:8000`.

---

## B. Operasi (Penggunaan API)

Semua request wajib menyertakan header:

```
Accept: application/json
```

### 1. Register

```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"name":"Bram","email":"bram@test.com","password":"secret123","password_confirmation":"secret123"}'
```

### 2. Login

```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"email":"bram@test.com","password":"secret123"}'
```

Response berisi `access_token` — dipakai untuk semua request yang butuh login.

### 3. Cek user login (me)

```bash
curl http://localhost:8000/api/auth/me \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <TOKEN>"
```

### 4. Refresh token

```bash
curl -X POST http://localhost:8000/api/auth/refresh \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <TOKEN>"
```

### 5. Logout

```bash
curl -X POST http://localhost:8000/api/auth/logout \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <TOKEN>"
```

### 6. List movie (public, tanpa token)

```bash
curl http://localhost:8000/api/movies -H "Accept: application/json"
```

### 7. Detail movie (public)

```bash
curl http://localhost:8000/api/movies/1 -H "Accept: application/json"
```

### 8. Tambah movie (perlu token)

```bash
curl -X POST http://localhost:8000/api/movies \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <TOKEN>" \
  -d '{"title":"Inception","genre":"Sci-Fi","duration":148,"release_date":"2010-07-16","rating":8.8}'
```

### 9. Update movie (perlu token)

```bash
curl -X PUT http://localhost:8000/api/movies/1 \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <TOKEN>" \
  -d '{"title":"Inception (2010)"}'
```

### 10. Hapus movie (perlu token)

```bash
curl -X DELETE http://localhost:8000/api/movies/1 \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <TOKEN>"
```

---

## Ringkasan Endpoint

| Method | URL | Auth |
|---|---|---|
| POST | `/api/auth/register` | Public |
| POST | `/api/auth/login` | Public |
| POST | `/api/auth/logout` | Token |
| POST | `/api/auth/refresh` | Token |
| GET | `/api/auth/me` | Token |
| GET | `/api/movies` | Public |
| GET | `/api/movies/{id}` | Public |
| POST | `/api/movies` | Token |
| PUT/PATCH | `/api/movies/{id}` | Token |
| DELETE | `/api/movies/{id}` | Token |
