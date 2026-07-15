# Laravel Movie API + JWT Auth

Dokumentasi setup CRUD Movie API dengan autentikasi JWT di Laravel, dijalankan via Docker (app + mysql + nginx).

## Stack

- Laravel 13
- PHP 8.3 (Docker)
- MySQL 8.4 (Docker)
- Nginx (Docker)
- `tymon/jwt-auth` 2.3.0

## Struktur Environment

- `laravel-app` — PHP-FPM container (port internal 9000)
- `laravel-mysql` — MySQL container (host port `3307` → container `3306`)
- `laravel-nginx` — web server (host port `8000` → container `80`)

Semua command Laravel/Composer dijalankan lewat container:

```bash
docker compose exec app php artisan <command>
docker compose exec app composer <command>
```

---

## 1. Setup Awal

### Install & jalankan container

```bash
docker compose up -d
docker compose ps
```

Kalau port `3306` bentrok dengan MySQL lain di host, ubah port host di `docker-compose.yml`:

```yaml
ports:
  - "3307:3306"
```

`.env` tetap pakai host `mysql` dan port `3306` (port internal Docker network), **bukan** `127.0.0.1:3307` — karena koneksi antar container pakai nama service, bukan port host.

```env
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=root
DB_PASSWORD=secret
```

### Fix permission storage/cache

```bash
docker compose exec app chown -R www-data:www-data storage bootstrap/cache
docker compose exec app chmod -R 775 storage bootstrap/cache
```

---

## 2. Model Movie & Migration

```bash
docker compose exec app php artisan make:model Movie -mcr
```

**Migration** (`database/migrations/xxxx_create_movies_table.php`) — Laravel 11+ pakai anonymous class:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movies', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('genre')->nullable();
            $table->integer('duration')->nullable();
            $table->date('release_date')->nullable();
            $table->decimal('rating', 3, 1)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movies');
    }
};
```

```bash
docker compose exec app php artisan migrate
```

**Model** (`app/Models/Movie.php`):

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Movie extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'description', 'genre', 'duration', 'release_date', 'rating',
    ];

    protected $casts = [
        'release_date' => 'date',
        'rating' => 'decimal:1',
    ];
}
```

**Form Request** (`app/Http/Requests/MovieRequest.php`):

```bash
docker compose exec app php artisan make:request MovieRequest
```

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MovieRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'genre' => 'nullable|string|max:100',
            'duration' => 'nullable|integer|min:1',
            'release_date' => 'nullable|date',
            'rating' => 'nullable|numeric|min:0|max:10',
        ];
    }
}
```

**Controller** (`app/Http/Controllers/MovieController.php`) — CRUD standar dengan response JSON konsisten (`success`, `message`, `data`).

---

## 3. JWT Auth Setup

### Install package

```bash
docker compose exec app composer require tymon/jwt-auth
docker compose exec app php artisan vendor:publish --provider="Tymon\JWTAuth\Providers\LaravelServiceProvider"
docker compose exec app php artisan jwt:secret
```

### Model User (`app/Models/User.php`)

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use Notifiable;

    protected $fillable = ['name', 'email', 'password'];
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }
}
```

### Config Auth (`config/auth.php`)

```php
'defaults' => [
    'guard' => 'api',
    'passwords' => 'users',
],

'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'users',
    ],

    'api' => [
        'driver' => 'jwt',
        'provider' => 'users',
    ],
],
```

### AuthController (`app/Http/Controllers/AuthController.php`)

Endpoint: `register`, `login`, `logout`, `refresh`, `me` — semua pakai `Auth::guard('api')`.

### Routes (`routes/api.php`)

```php
<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\MovieController;
use Illuminate\Support\Facades\Route;

// Public: auth
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

// Public: lihat data movie
Route::get('/movies', [MovieController::class, 'index']);
Route::get('/movies/{movie}', [MovieController::class, 'show']);

// Protected: perlu token
Route::middleware('auth:api')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    Route::post('/movies', [MovieController::class, 'store']);
    Route::put('/movies/{movie}', [MovieController::class, 'update']);
    Route::patch('/movies/{movie}', [MovieController::class, 'update']);
    Route::delete('/movies/{movie}', [MovieController::class, 'destroy']);
});
```

**Penting:** `routes/api.php` tidak otomatis ter-load di Laravel 11+. Harus didaftarkan di `bootstrap/app.php`:

```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    api: __DIR__.'/../routes/api.php',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
)
```

### Exception handling JSON (`bootstrap/app.php`)

```php
->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->shouldRenderJsonWhen(
        fn (Request $request) => $request->is('api/*'),
    );

    $exceptions->render(function (TokenExpiredException $e, $request) {
        return response()->json(['success' => false, 'message' => 'Token sudah expired'], 401);
    });

    $exceptions->render(function (TokenInvalidException $e, $request) {
        return response()->json(['success' => false, 'message' => 'Token tidak valid'], 401);
    });

    $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
        if ($request->is('api/*')) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }
    });
})
```

---

## 4. Testing

Semua request API sebaiknya menyertakan header `Accept: application/json`, kalau tidak, Laravel bisa treat request sebagai non-API dan coba redirect ke route `login` yang tidak ada (khusus di project API-only).

### Register

```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"name":"Bram","email":"bram@test.com","password":"secret123","password_confirmation":"secret123"}'
```

### Login

```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"email":"bram@test.com","password":"secret123"}'
```

### List movie (public)

```bash
curl http://localhost:8000/api/movies -H "Accept: application/json"
```

### Create movie (perlu token)

```bash
curl -X POST http://localhost:8000/api/movies \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <TOKEN>" \
  -d '{"title":"Inception","genre":"Sci-Fi","duration":148,"release_date":"2010-07-16","rating":8.8}'
```

### Cek user login

```bash
curl http://localhost:8000/api/auth/me \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <TOKEN>"
```

### Logout

```bash
curl -X POST http://localhost:8000/api/auth/logout \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <TOKEN>"
```

---

## 5. Endpoint Summary

| Method | URL | Auth | Fungsi |
|---|---|---|---|
| POST | `/api/auth/register` | Public | Daftar user baru |
| POST | `/api/auth/login` | Public | Login, dapat token |
| POST | `/api/auth/logout` | Protected | Logout |
| POST | `/api/auth/refresh` | Protected | Refresh token |
| GET | `/api/auth/me` | Protected | Data user login |
| GET | `/api/movies` | Public | List semua movie |
| GET | `/api/movies/{id}` | Public | Detail movie |
| POST | `/api/movies` | Protected | Tambah movie |
| PUT/PATCH | `/api/movies/{id}` | Protected | Update movie |
| DELETE | `/api/movies/{id}` | Protected | Hapus movie |

---

## 6. Troubleshooting Log (masalah yang pernah muncul & solusinya)

| Masalah | Penyebab | Solusi |
|---|---|---|
| `composer require` gagal, `ext-xml`/`ext-dom` missing | Extension PHP belum aktif di host | `sudo apt install php8.3-xml` |
| `Permission denied` saat `composer dump-autoload` (host) | Ownership folder `storage`/`bootstrap/cache` salah | `sudo chown -R $USER:$USER storage bootstrap/cache` |
| `Class "CreateMoviesTable" not found` | Migration pakai syntax lama (`class X extends Migration`) | Laravel 11+ pakai anonymous class: `return new class extends Migration {...}` |
| `could not find driver (mysql)` | Extension `pdo_mysql` belum ada / MySQL belum jalan | Install `php8.3-mysql`, pastikan MySQL server/container jalan |
| `address already in use :3306` | Port 3306 dipakai proses lain | Ganti port host di `docker-compose.yml`, mis. `3307:3306` |
| `Permission denied` storage (dalam container) | Owner file di container bukan `www-data` | `docker compose exec app chown -R www-data:www-data storage bootstrap/cache` |
| 404 Not Found di semua route `/api/*` | `routes/api.php` belum didaftarkan di `bootstrap/app.php` | Tambahkan `api: __DIR__.'/../routes/api.php'` di `withRouting()` |
| `Class ...\Api\MovieController does not exist` | Namespace di file tidak sesuai lokasi folder | Samakan `namespace` dengan struktur folder (PSR-4) |
| `Cannot declare class ... because name already in use` | Namespace ganda / cache lama | `composer dump-autoload` + `route:clear` |
| `Auth guard [api] is not defined` | Guard `api` belum dikonfigurasi | Tambahkan guard `api` dengan driver `jwt` di `config/auth.php` |
| `Route [login] not defined` | Request tidak ada header `Accept: application/json`, dianggap request web | Selalu sertakan header `Accept: application/json` |

---

## 7. Pengembangan Lanjutan (opsional)

- **API Resource** (`MovieResource`) untuk kontrol response yang lebih rapi
- **Search/filter**: `GET /api/movies?genre=Sci-Fi&search=inception`
- **Role-based access**: kolom `role` di tabel users + middleware custom
- **Rate limiting** di routes
- **Upload poster movie**: sesuaikan `client_max_body_size` di config nginx
- **CORS**: atur di `config/cors.php` kalau nanti dipanggil dari frontend beda domain
- **SSL/HTTPS**: perlu setup nginx tambahan saat deploy ke production
