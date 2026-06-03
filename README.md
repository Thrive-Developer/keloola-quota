# Keloola Quota

Package Laravel untuk mengelola **billing quota** per-app. Mendukung dua tipe quota:

| Type | Perilaku | Contoh |
|------|----------|--------|
| `snapshot` | Quota **tetap/fixed**. Nilai usage mencerminkan kondisi saat ini, naik-turun mengikuti data, **tidak pernah** di-reset otomatis. | Jumlah user, storage space, jumlah hardware, jumlah ebook |
| `counter` | Quota yang **di-reset setiap bulan** (per periode billing). Hanya bertambah sampai reset. | Jumlah transaksi, automation run |

Terintegrasi dengan model `QuotaMetric` dan `AppPlanQuota` dari ERD Pricing & Subscription Anda.

## Instalasi

Install via composer:

```bash
composer require keloola/quota
```

Publish & jalankan migration:

```bash
php artisan vendor:publish --tag=keloola-quota-config
php artisan vendor:publish --tag=keloola-quota-migrations
php artisan migrate
```

> Migration juga otomatis ter-load lewat `loadMigrationsFrom`, jadi `php artisan migrate` bisa langsung jalan tanpa publish.

## Konfigurasi referensi tabel

Package **tidak memiliki** tabel `apps`, `app_plans`, dan `organizations`. Di `config/keloola-quota.php` atur tipe kolom dan apakah pakai foreign key constraint:

```php
'references' => [
    'app'          => ['table' => 'apps',          'column' => 'id', 'type' => 'unsignedBigInteger'],
    'app_plan'     => ['table' => 'app_plans',     'column' => 'id', 'type' => 'unsignedBigInteger'],
    'organization' => ['table' => 'organizations', 'column' => 'id', 'type' => 'uuid'],
    'constrained'  => false, // set true bila tabel referensi ada di DB yang sama
],
```

Di setup SSO/distributed (tabel apps ada di service lain), biarkan `constrained => false`.

## Tabel yang dibuat

- `quota_metrics` — definisi metrik per app (`type` = snapshot|counter)
- `app_plan_quotas` — limit aktual tiap metrik per plan (`limit`, `is_unlimited`)
- `quota_usages` — pemakaian per organization per metrik (counter punya `period_key`)
- `quota_usage_logs` — audit trail setiap perubahan

## Penggunaan

```php
use Keloola\Quota\Facades\Quota;

// Cek apakah masih bisa menambah transaksi
$can = Quota::canConsume('transactions', 1);

// Catat 1 transaksi (counter — akan reset bulan depan)
Quota::increment('transactions', 1, ['ref' => $invoice->id]);

// Tambah user (snapshot — naik turun, tidak di-reset)
Quota::increment('users');

// User dihapus
Quota::decrement('users');

// Set absolut (cocok untuk storage yang dihitung ulang dari file)
Quota::set('storage_mb', 4096);

// Sisa & laporan
$sisa   = Quota::remaining('storage_mb'); // null = unlimited
$report = Quota::report();
```

Bila `config('keloola-quota.strict')` `true` (default), `increment` yang melewati limit melempar `QuotaExceededException`:

```php
use Keloola\Quota\Exceptions\QuotaExceededException;

try {
    Quota::increment('transactions');
} catch (QuotaExceededException $e) {
    return response()->json(['message' => 'Kuota transaksi habis untuk bulan ini'], 429);
}
```

## Context Middleware (Otomatis dari JWT SSO)

Bila aplikasi satelit Anda menerima request dengan **Bearer JWT Token dari SSO**, Anda dapat menggunakan middleware `keloola.quota.context`. Middleware ini akan otomatis menembak endpoint SSO (`/api/jwt/user`), menarik data profil, dan men-set konteks quota (App ID, Organization ID, dan Plan ID) ke *Facade*.

```php
// di rute aplikasi satelit Anda
Route::middleware(['keloola.quota.context'])->group(function () {
    Route::post('/transactions', function () {
        // Konteks app, org, dan plan sudah di-set oleh middleware
        // sehingga Anda cukup memanggil metriknya saja:
        Quota::increment('transactions');
    });
});
```

Pastikan Anda mengatur konfigurasi SSO di `.env` aplikasi satelit:
```env
KELOOLA_AUTH_SSO_HOST=http://sso-keloola.test
KELOOLA_AUTH_APP_ID=2
```

> **Catatan Validasi:** 
> - Middleware ini secara otomatis memvalidasi token JWT. Jika token kosong atau tidak valid, middleware akan mengembalikan pesan error `401 Unauthorized`.
> - Middleware akan mencocokkan `KELOOLA_AUTH_APP_ID` dengan data `applications` pada JWT. Jika aplikasi tidak ditemukan atau array aplikasi kosong, akan dikembalikan pesan error `403 Forbidden`.

## Check Quota Middleware

Selain memanggil `Quota::canConsume()` secara manual di controller, Anda juga bisa menggunakan middleware `keloola.quota.check` untuk memblokir request di level *route* jika kuota tidak mencukupi. Middleware ini menerima parameter berupa `metric_code` dan opsional `amount` (default: 1).

**Penting:** Anda harus meletakkan middleware `keloola.quota.context` sebelum middleware ini agar konteks kuota sudah disiapkan.

```php
// Cek apakah masih memiliki kuota transactions (amount: 1)
Route::post('/transactions', [TransactionController::class, 'store'])
    ->middleware(['keloola.quota.context', 'keloola.quota.check:transactions']);

// Cek dengan amount yang spesifik (misal butuh 5 kuota)
Route::post('/bulk-transactions', [TransactionController::class, 'bulkStore'])
    ->middleware(['keloola.quota.context', 'keloola.quota.check:transactions,5']);
```

Jika kuota tidak cukup, akan muncul response JSON (429 Too Many Requests). Pesan error akan menyesuaikan bahasa yang diatur pada aplikasi (contoh: `config/app.php` locale `id`):
```json
{
    "message": "Anda telah melewati batas kuota untuk 'transactions'. Silakan tingkatkan paket Anda."
}
```

## Reset counter bulanan

Jadwalkan command di `routes/console.php` (Laravel 11+) atau `Kernel`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('keloola-quota:reset-counters')->monthlyOn(1, '00:05');
```

Reset juga terjadi *lazy*: saat usage counter diakses pada periode baru, `period_key` otomatis berganti dan nilai mulai dari 0. Command hanya untuk membersihkan/mencatat secara eksplisit.

## Plan & Metrik default (QuotaSeeder)

Seeder `QuotaSeeder` mendefinisikan metrik dan limit untuk 5 app. **Sesuaikan map `$apps` dan `$plans` dengan `id` asli** di DB Anda, lalu:

```bash
php artisan db:seed --class="Keloola\Quota\Database\Seeders\QuotaSeeder"
```

| App | Metrik | Type |
|-----|--------|------|
| Accounting | Jumlah Transaksi | counter |
| | Jumlah User | snapshot |
| | Jumlah Invoice, Jurnal | counter |
| Cloud Storage | Storage Space (MB), Jumlah File, User | snapshot |
| POS | Jumlah Transaksi | counter |
| | Jumlah Outlet, Produk | snapshot |
| Ebook | Jumlah Ebook | snapshot |
| Automate | Jumlah Hardware, User | snapshot |
| | Jumlah Automation Run | counter |

Limit per plan (Basic / Pro / Enterprise) ada di dalam `QuotaSeeder`; Enterprise umumnya `is_unlimited = true`.

## Provisioning saat instalasi (SSO push)

Alih-alih menjalankan seeder manual di tiap app, SSO dapat **mendorong (push)** definisi quota ke app saat sebuah organization meng-install app, dan kembali mendorong update saat plan/quota berubah. App menyimpan definisi ini lokal (`quota_metrics` + `app_plan_quotas`).

### Sisi app (otomatis dari package)

Package mendaftarkan endpoint berikut secara otomatis:

```
POST   /api/quota/provision          # install + update (idempotent / upsert)
DELETE /api/quota/provision/{planId} # uninstall / plan dihapus
```
Secara default, endpoint dilindungi middleware `keloola.quota.sso` (dapat disesuaikan secara dinamis melalui `config('keloola-quota.provisioning.middleware')`).

### Payload yang dikirim SSO

```json
{
  "app_id": 2,
  "app_plan_id": 202,
  "metrics": [
    { "name": "Storage Space", "code": "storage_mb", "type": "snapshot", "unit": "MB", "limit": 102400, "is_unlimited": false },
    { "name": "Jumlah User",   "code": "users",      "type": "snapshot", "unit": "users", "limit": 10, "is_unlimited": false }
  ]
}
```

Karena operasinya **upsert**, payload yang sama aman dikirim berulang — install pertama mengisi, perubahan plan memperbarui. Tidak perlu logika "sudah ada atau belum" di sisi SSO.

### Sisi SSO

Contoh dispatcher ada di `examples/sso-side/QuotaProvisioningDispatcher.php` (bukan bagian package — disalin ke project keloola-sso). Pemicunya:

```php
// Saat organization install app
app(QuotaProvisioningDispatcher::class)->pushPlan($appPlan);

// Otomatis saat limit diubah, lewat observer AppPlanQuota
app(QuotaProvisioningDispatcher::class)->pushPlan($quota->appPlan);
```

Bungkus dalam queued job agar tidak memblok request bila app satelit lambat/down.

> **Catatan**: provisioning ini hanya untuk **definisi quota (limit per plan)**. Status subscription aktif/expired tetap dicek terpisah (mis. API call + cache ke SSO), karena status berubah lebih dinamis daripada definisi limit.

## Pengecekan Quota Lintas Aplikasi (Cross-App)

Kadang-kadang satu aplikasi (misalnya SSO atau aplikasi lainnya) perlu mengecek limit quota dari aplikasi lain. Package ini menyediakan endpoint bawaan dan service khusus untuk kebutuhan tersebut.

### Endpoint Pengecekan Quota

Package ini secara otomatis mengekspos endpoint `GET /api/quota/check/{metricCode}` yang mengembalikan status penggunaan quota untuk suatu metrik secara langsung (lengkap dengan limit, used, remaining, dan lain-lain).

```json
// GET /api/quota/check/storage_space
{
    "status": "ok",
    "data": {
        "code": "storage_space",
        "name": "Storage Space",
        "type": "snapshot",
        "unit": "MB",
        "used": 500,
        "limit": 1024,
        "is_unlimited": false,
        "remaining": 524,
        "percent": 48.8
    }
}
```

### QuotaCheckService

Untuk melakukan panggilan ke endpoint tersebut dari backend PHP Anda, package ini menyediakan `Keloola\Quota\Services\QuotaCheckService`. Service ini dapat menangani pemanggilan lintas aplikasi (Cross-App) secara aman dan sudah diregistrasikan sebagai Singleton di Service Provider.

#### Penggunaan via API URL & Token Dinamis
```php
use Keloola\Quota\Services\QuotaCheckService;

$service = app(QuotaCheckService::class);
$response = $service->checkQuota('https://app.keloola.in', 'storage_space', $jwtToken);

if ($response && $response['status'] === 'ok') {
    $remaining = $response['data']['remaining'];
    // ... logic
}
```

#### Pengaturan Konfigurasi Cross-App (Misal: Storage)
Package ini juga mendukung konfigurasi untuk layanan spesifik di `config/keloola-quota.php`. Anda bisa mengatur `api_url` dan `metric_code` via `.env`.

**`config/keloola-quota.php`**:
```php
'storage' => [
    'api_url'     => env('KELOOLA_FILE_API_URL', 'https://file.keloola.in'),
    'metric_code' => env('KELOOLA_FILE_METRIC_CODE', 'storage_space'),
],
```

**Penggunaan di Service**:
```php
$apiUrl = config('keloola-quota.storage.api_url');
$metricCode = config('keloola-quota.storage.metric_code');

$response = app(QuotaCheckService::class)->checkQuota($apiUrl, $metricCode, $jwtToken);
```
