# Instalasi & Operasi — Movie Watchlist API (Laravel + JWT + Sample API)

Aplikasi consume data movie dari [Sample APIs](https://api.sampleapis.com/movies/comedy) → disimpan ke database lokal (tabel master `movies`) → user bisa kelola watchlist pribadi (tabel transaksional `watchlists`) lewat API + dashboard Blade.

## A. Instalasi

> `composer install` dan fix permission (`chown`/`chmod` storage & bootstrap/cache) **sudah otomatis** dijalankan lewat `Dockerfile` dan `docker-entrypoint.sh` setiap container start — tidak perlu dijalankan manual.

### 1. Jalankan container

```bash
docker compose up -d --build
```

Urutan startup otomatis: `mysql` harus `healthy` dulu (healthcheck `mysqladmin ping`) sebelum `app` start. `app` sendiri punya healthcheck PHP-FPM (`pgrep`).

Cek status:

```bash
docker compose ps
```

Pastikan `laravel-app` dan `laravel-mysql` berstatus `(healthy)`.

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

Membuat tabel: `users`, `movies` (master), `watchlists` (transaksional).

### 5. Fetch data movie dari Sample API ke database lokal

```bash
docker compose exec app php artisan movies:fetch comedy
```

Genre lain yang tersedia: `classic`, `western`, `family`, `mystery`, `scifi-fantasy`, `animation`, `horror`, `drama`. (Genre `action-adventure` sedang bermasalah di sisi server Sample API, hindari dulu.)

Command ini **idempotent** — aman dijalankan berkali-kali (upsert berdasarkan `external_id` + `genre`), dan otomatis retry 3x kalau kena rate limit (429) atau server error (5xx).

### 6. Clear cache (hanya kalau ada perubahan config/route setelah setup awal)

```bash
docker compose exec app php artisan route:clear
docker compose exec app php artisan config:clear
```

Aplikasi siap diakses:
- **Dashboard**: `http://localhost:8000/login`
- **API**: `http://localhost:8000/api`

---

## B. Operasi (Penggunaan API)

Semua request wajib menyertakan header:

```
Accept: application/json
```

### Auth

**Register**
```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"name":"Bram","email":"bram@test.com","password":"secret123","password_confirmation":"secret123"}'
```

**Login**
```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"email":"bram@test.com","password":"secret123"}'
```
Response berisi `access_token` — dipakai di header `Authorization: Bearer <TOKEN>` untuk semua request yang butuh login.

**Cek user login**
```bash
curl http://localhost:8000/api/auth/me \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <TOKEN>"
```

**Refresh token**
```bash
curl -X POST http://localhost:8000/api/auth/refresh \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <TOKEN>"
```

**Logout**
```bash
curl -X POST http://localhost:8000/api/auth/logout \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <TOKEN>"
```

### Movies (data master — read & delete saja)

**List (support filter `genre`, `search`, pagination)**
```bash
curl "http://localhost:8000/api/movies?search=Lady&genre=comedy" -H "Accept: application/json"
```

**Detail**
```bash
curl http://localhost:8000/api/movies/1 -H "Accept: application/json"
```

**Hapus (perlu token)**
```bash
curl -X DELETE http://localhost:8000/api/movies/1 \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <TOKEN>"
```

### Watchlists (data transaksional milik user — full CRUD, semua perlu token)

**List watchlist saya**
```bash
curl http://localhost:8000/api/watchlists \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <TOKEN>"
```

**Tambah ke watchlist**
```bash
curl -X POST http://localhost:8000/api/watchlists \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <TOKEN>" \
  -d '{"movie_id":2,"status":"plan_to_watch","notes":"Ditonton weekend ini"}'
```
`status` valid: `plan_to_watch`, `watching`, `watched`. Duplikat movie di watchlist yang sama akan ditolak (`409`).

**Detail**
```bash
curl http://localhost:8000/api/watchlists/1 \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <TOKEN>"
```

**Update**
```bash
curl -X PUT http://localhost:8000/api/watchlists/1 \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <TOKEN>" \
  -d '{"movie_id":2,"status":"watched","rating":8,"notes":"Bagus banget"}'
```

**Hapus**
```bash
curl -X DELETE http://localhost:8000/api/watchlists/1 \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <TOKEN>"
```

> Ownership dicek otomatis — user hanya bisa lihat/edit/hapus watchlist miliknya sendiri (`403` kalau coba akses milik user lain).

### Monitoring

**Health check (public, tanpa token)**
```bash
curl http://localhost:8000/api/health -H "Accept: application/json"
```
Mengecek koneksi database, balikin `200` (ok) atau `503` (degraded).

---

## C. Dashboard (Frontend Blade)

Buka di browser: `http://localhost:8000/login`

- Login/register lewat form (token JWT disimpan di `localStorage`)
- Tab **Semua Movie** — browse data master hasil fetch dari Sample API, tombol "+ Watchlist"
- Tab **Watchlist Saya** — kelola watchlist pribadi (edit status/rating/notes, hapus)

---

## D. Logging & Monitoring

Semua log tersimpan di `storage/logs/`, terpisah per jenis:

| File | Isi |
|---|---|
| `api-YYYY-MM-DD.log` | Setiap request API: method, endpoint, status, response time, user_id, IP |
| `transactions-YYYY-MM-DD.log` | Setiap create/update/delete watchlist |
| `alerts-YYYY-MM-DD.log` | Slow response (>2000ms) dan error rate tinggi (>3x error/endpoint dalam 5 menit) |
| `laravel.log` | Error umum & exception default Laravel |

Cek log terbaru:
```bash
docker compose exec app tail -f storage/logs/api-$(date +%Y-%m-%d).log
docker compose exec app tail -f storage/logs/alerts-$(date +%Y-%m-%d).log
```

---

## E. Ringkasan Endpoint

| Method | URL | Auth | Keterangan |
|---|---|---|---|
| POST | `/api/auth/register` | Public | Daftar user |
| POST | `/api/auth/login` | Public | Login, dapat token |
| POST | `/api/auth/logout` | Token | Logout |
| POST | `/api/auth/refresh` | Token | Refresh token |
| GET | `/api/auth/me` | Token | Data user login |
| GET | `/api/movies` | Public | List movie (filter `genre`, `search`) |
| GET | `/api/movies/{id}` | Public | Detail movie |
| DELETE | `/api/movies/{id}` | Token | Hapus movie |
| GET | `/api/watchlists` | Token | List watchlist milik user |
| POST | `/api/watchlists` | Token | Tambah watchlist |
| GET | `/api/watchlists/{id}` | Token | Detail watchlist |
| PUT/PATCH | `/api/watchlists/{id}` | Token | Update watchlist |
| DELETE | `/api/watchlists/{id}` | Token | Hapus watchlist |
| GET | `/api/health` | Public | Health check (DB) |

---

## F. Command Artisan Kustom

| Command | Fungsi |
|---|---|
| `movies:fetch {genre=comedy}` | Fetch data movie dari Sample API, upsert ke tabel `movies`. Retry otomatis untuk 429/5xx. |

---

## G. Infrastruktur & Reliability (Docker)

### Struktur container

| Service | Image | Port | Healthcheck |
|---|---|---|---|
| `app` | custom (`php:8.3-fpm`) | 9000 (internal) | `pgrep` cek proses PHP-FPM masih hidup |
| `nginx` | `nginx:alpine` | `8000:80` | - |
| `mysql` | `mysql:8.4` | `3307:3306` (host) | `mysqladmin ping` |

### Dependency ordering

`app` menunggu `mysql` berstatus **healthy** (bukan cuma "container jalan") sebelum ikut start — mencegah race condition migrate/query gagal karena database belum siap menerima koneksi:

```yaml
depends_on:
  mysql:
    condition: service_healthy
```

### Auto-recovery

Semua service diset `restart: unless-stopped` — kalau container crash, Docker otomatis restart tanpa intervensi manual.

### Permission handling otomatis

`docker-entrypoint.sh` pada container `app` otomatis menjalankan `chown`/`chmod` ke `storage/` dan `bootstrap/cache/` setiap kali container start, sehingga tidak perlu fix permission manual.

### Rebuild image (kalau ada perubahan Dockerfile)

```bash
docker compose build app
docker compose up -d
```

### Cek status kesehatan seluruh service

```bash
docker compose ps
```

Semua service target harus menunjukkan `(healthy)`:

```
laravel-app     Up XX seconds (healthy)
laravel-mysql   Up XX minutes (healthy)
laravel-nginx   Up XX minutes
```

### Cek detail histori healthcheck (debugging)

```bash
docker inspect laravel-app | grep -A 30 '"Health"'
```
