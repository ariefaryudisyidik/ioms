# IOMS — Inventory & Order Management System

Aplikasi PHP native (tanpa framework) untuk mengelola inventori, Purchase Order (PO), Sales Order (SO), dan pelaporan stok berbasis multi-gudang.

## Fitur

- Autentikasi berbasis session dengan 3 role: `Admin`, `Sales`, `WarehouseStaff`.
- Manajemen master data: User, Produk (SKU, kategori, harga, reorder point, gambar), Kategori, Supplier, Customer, Gudang.
- Purchase Order: create → Draft → Ordered → PartiallyReceived/Received, goods receipt parsial per item, pencatatan `stock_ledger`.
- Sales Order: create → Draft → PendingApproval → Approved → Fulfilled, alur approval dengan pemisahan peran (pembuat ≠ penyetuju, penyetuju wajib Admin), goods issue race-safe memakai `SELECT ... FOR UPDATE`.
- Dashboard ringkasan berbasis role.
- Laporan ekspor CSV (stock ledger, status order) dengan filter tanggal.
- REST endpoint internal: `GET /api/products/{sku}/availability` untuk cek ketersediaan stok per SKU.
- Skrip CLI `scripts/check-low-stock.php` untuk memeriksa produk di bawah reorder point (dijadwalkan lewat cron sistem operasi, bukan scheduler bawaan aplikasi).

## Requirement

- PHP >= 8.2
- MySQL 8
- Composer
- Docker & Docker Compose (opsional, untuk menjalankan aplikasi + MySQL tanpa instalasi manual, dan untuk integration test)

## Instalasi

### Opsi A — dengan Docker

```bash
git clone <repo-url> ioms
cd ioms
cp .env.example .env    # wajib; ganti DB_PASSWORD sebelum dipakai di luar demo lokal
docker compose up --build
```

Aplikasi tersedia di http://localhost:8080 dan MySQL di port host 3307. Schema dan seed data (`database/schema.sql`, `database/seed.sql`) diimpor otomatis saat volume MySQL pertama kali dibuat. Untuk mengulang dari data bersih: `docker compose down -v && docker compose up --build`.

Container memakai kode dan `vendor/` dari image (tanpa bind mount), sehingga perubahan kode memerlukan `docker compose up --build`.

### Opsi B — manual tanpa Docker

```bash
git clone <repo-url> ioms
cd ioms
composer install
cp .env.example .env
# edit .env: DB_HOST, DB_NAME, DB_USER, DB_PASS sesuai instance MySQL lokal Anda
mysql -u <user> -p < database/schema.sql
mysql -u <user> -p <nama_db> < database/seed.sql
php -S localhost:8000 -t public   # sesuaikan document root sesuai struktur public/
```

## Akun Demo

Seluruh akun demo memakai password: **`Password123!`**

(Sumber: `database/seed.sql` — hash bcrypt yang sama untuk semua baris demo.)

| Role | Nama | Email |
|---|---|---|
| Admin | Andi Wijaya | admin@ioms.test |
| Sales | Sari Dewi | sari.sales@ioms.test |
| Sales | Budi Santoso | budi.sales@ioms.test |
| WarehouseStaff | Rudi Hartono | rudi.warehouse@ioms.test |
| WarehouseStaff | Maya Putri | maya.warehouse@ioms.test |

## Menjalankan Test

```bash
composer test                       # menjalankan seluruh suite phpunit
composer test:unit                  # hanya suite Unit (memakai InMemory*Repository, tidak butuh DB)
vendor/bin/phpunit --testsuite Unit
vendor/bin/phpunit --testsuite Integration   # butuh MySQL nyata (mis. lewat docker compose) yang sudah terisi schema
composer coverage                   # Unit + Integration + E2E sekaligus, lengkap dengan coverage (lihat di bawah)
```

Integration test menggunakan implementasi `MySql*Repository` dan membutuhkan koneksi database sungguhan — jalankan `docker compose up -d` (atau siapkan MySQL lokal dengan `database/schema.sql` sudah diimpor) sebelum menjalankan suite ini.

Suite **E2E** (`tests/E2E`) menguji aplikasi lewat HTTP (login, CRUD, alur PO/SO, laporan, API, skrip cron) dan akan dilewati otomatis bila `E2E_BASE_URL` tidak di-set. `composer coverage` (`scripts/coverage.sh`) menyalakan MySQL sementara (Docker, tmpfs) dan server PHP built-in dengan perekam coverage per-request, menjalankan ketiga suite, lalu menggabungkan hasilnya (phpcov) menjadi `build/coverage/clover.xml`. Butuh Docker dan PHP dengan Xdebug.

## Static Analysis

```bash
vendor/bin/phpstan analyse --no-progress
vendor/bin/phpcs --standard=phpcs.xml app/
```

Hasil run terakhir tersimpan di `docs/quality/static-analysis-report.txt` — PHPStan level 5: **0 error**. PHPCS (PSR-12): **0 error**, sisa 28 warning "line exceeds 120 characters" (kosmetik, tidak memengaruhi fungsi/keterbacaan pada baris terkait array literal yang tetap dijaga tidak dipecah demi keterbacaan array asosiatif).

## SonarQube

SonarQube Community lokal dijalankan lewat `docker-compose.sonar.yml` (dashboard di http://localhost:9001, login awal `admin`/`admin`).

```bash
docker compose -f docker-compose.sonar.yml up -d sonarqube   # tunggu status UP
# buat token: My Account > Security, lalu export SONAR_TOKEN=<token>

# 1. Coverage gabungan Unit + Integration + E2E (path sudah dipetakan ke /usr/src untuk scanner)
composer coverage

# 2. Scan
docker compose -f docker-compose.sonar.yml run --rm scanner sonar-scanner -Dsonar.qualitygate.wait=true
```

Konfigurasi analisis ada di `sonar-project.properties`. Hasil dan penjelasan temuan: `docs/quality/sonarqube-report.md`.

## Keamanan

Kontrol yang aktif (detail, bukti test, dan risiko tersisa: `docs/quality/security-review.md`, ADR-004):

- **CSRF:** token per-sesi pada semua request state-changing (disisipkan otomatis ke form).
- **Sesi:** cookie `HttpOnly` + `SameSite=Lax` (+ `Secure` di HTTPS), idle timeout 30 menit, umur maksimum 8 jam (`SESSION_IDLE_TIMEOUT`, `SESSION_ABSOLUTE_TIMEOUT` di `.env`), dan user divalidasi ulang ke database di setiap request.
- **Login:** bcrypt, throttling (5 gagal per akun+IP atau 20 per IP dalam 15 menit), respons waktu-konstan untuk akun yang tidak ada.
- **Otorisasi:** role dicek di server, plus kepemilikan objek (Sales hanya melihat/membatalkan SO miliknya; tidak bisa menyetujui SO sendiri; admin tidak bisa menonaktifkan akunnya sendiri).
- **Input/output:** prepared statement, validasi referensi/harga/qty/tanggal, upload diverifikasi (`mime` + `getimagesize`, maks 2MB, nama acak), output di-escape, CSP `script-src 'self'`, CSV dinetralkan dari formula.
- **Container:** non-root, multi-stage tanpa tool dev, PHP dimatikan di folder upload, versi server disembunyikan, MySQL hanya di `127.0.0.1`.

Produksi: pasang TLS di depan aplikasi (reverse proxy), ganti semua kredensial di `.env`, dan hapus/ganti akun demo.

## Dokumentasi

- Perencanaan: `docs/planning/` (user story, scope, ERD, class diagram awal, backlog)
- Arsitektur: `docs/architecture/` (class diagram as-built, ADR repository pattern, ADR concurrency-safe stock, ADR strategi test E2E/coverage, ADR kontrol keamanan, ADR TransactionManager)
- Kualitas kode: `docs/quality/` (refactor log, audit SRP, tech debt, critique, laporan static analysis, laporan SonarQube, tinjauan keamanan)
- Testing: `docs/testing/` (skenario test, known bugs)
- Disclosure penggunaan AI: `ai-usage-log.md`

## Asset Pihak Ketiga

- Ikon: [Lucide](https://lucide.dev) (`lucide-static` v1.52.0, lisensi ISC). File SVG disalin ke `public/assets/icons/` (lisensi di `public/assets/icons/LICENSE`) dan di-inline lewat helper `icon()`; tidak ada library JS atau CDN saat runtime.

## Known Limitations (Jujur)

- `ApiController` mengakses tiga `MySql*Repository` secara langsung tanpa lewat Service — penyimpangan kecil dari pola layering di controller lain.
- Rate limiting hanya untuk login; endpoint API belum dibatasi lajunya.
- Otentikasi API memakai session cookie yang sama dengan web, bukan token terpisah.
- Implementasi `InMemory*Repository` (test double) tidak mensimulasikan row-locking MySQL sungguhan — skenario race condition goods-issue divalidasi lewat integration test terhadap MySQL asli di Docker (`tests/Integration/GoodsIssueIntegrationTest.php`), bukan lewat thread/proses paralel sungguhan (sesuai batasan brief — tidak wajib).
- Tidak ada CI/CD pipeline (di luar scope sesuai brief — lihat `docs/planning/scope.md`).
- Validasi input bersifat dasar (required/format/uniqueness/angka non-negatif), belum ada validasi lintas-field yang kompleks.
- Detail lengkap ada di `docs/quality/tech-debt.md`.

## Checklist Status Fitur

Status berikut sudah diverifikasi ulang secara end-to-end (bukan cuma dibaca kodenya) per 2026-09-02: `docker compose up --build` dari volume bersih, login ketiga role, CRUD master data, alur PO (create → goods receipt), alur SO (create → submit → approve oleh Admin → goods issue oleh Warehouse → Fulfilled), segregation of duties (Sales ditolak 403 saat mencoba approve order sendiri maupun akses `/users`), endpoint `GET /api/products/{sku}/availability` (200/401/404), dashboard ketiga role, ekspor CSV, skrip `check-low-stock.php`, serta full test suite (26 test/63 assertion, termasuk 4 integration test terhadap MySQL nyata) dan static analysis (PHPStan 0 error, PHPCS 0 error).

| Kode | Fitur | Status |
|---|---|---|
| AUTH-01/02 | Login/logout, proteksi role | Done & diverifikasi via Docker |
| USR-01 | CRUD user | Done & diverifikasi |
| PRD-01 | CRUD produk | Done & diverifikasi |
| WH-01 | Gudang & stok multi-gudang | Done & diverifikasi |
| PO-01 | Purchase Order + goods receipt | Done & diverifikasi |
| SO-01 | Sales Order + approval + fulfill | Done & diverifikasi (termasuk segregation of duties di server) |
| VIEW-01 | Tampilan web (list/detail/empty state) | Done |
| FIND-01 | Pencarian/filter/paginasi | Done & diverifikasi |
| DASH-01 | Dashboard per role | Done & diverifikasi |
| REPORT-01 | Laporan CSV | Done & diverifikasi |
| API-01 | API availability | Done & diverifikasi (200/401/404) |
| VAL-01 | Validasi domain (frontend+backend) | Done |
| ERR-01 | Penanganan error (401/403/404, no stack trace) | Done & diverifikasi |
| UI-01 | Layout responsive, old input, flash | Done |
| DB-01 | Skema DB + seed | Done & diverifikasi |
| JOB-01 | Skrip low-stock via cron OS | Done & diverifikasi (`docker compose exec app php scripts/check-low-stock.php`) |
| ARCH-01/02 | Layered architecture, concurrency-safe stock | Done (lihat ADR di `docs/architecture/`) |
| TEST-01/02/03 | Unit, integration, static analysis | Done — 252 test lulus (1216 assertion), line coverage 100%, 0 error static analysis, SonarQube 0 isu terbuka dan 0% duplikasi (lihat `docs/quality/sonarqube-report.md`) |

Lihat `docs/planning/backlog.md` untuk rincian lebih lengkap per fitur.

## Yang Perlu Anda Verifikasi Sendiri Sebelum Submission

Seluruh item di atas sudah diverifikasi berjalan pada environment pengerjaan ini (macOS + Docker Desktop). Sebelum submission resmi, tetap disarankan Anda menjalankan ulang di mesin/lingkungan Anda sendiri untuk memastikan tidak ada asumsi environment yang meleset:

1. `docker compose down -v && docker compose up --build` dari clone bersih, lalu login ketiga role.
2. `composer test` untuk unit test (tidak butuh DB), dan `vendor/bin/phpunit --testsuite Integration` dengan `TEST_DB_*` mengarah ke container MySQL (lihat `tests/Integration/IntegrationTestCase.php`) untuk integration test.
3. Baca `docs/quality/tech-debt.md` dan `docs/testing/known-bugs.md` untuk keterbatasan yang sudah dicatat jujur.
4. Pastikan Anda memahami dan bisa menjelaskan sendiri isi `docs/architecture/adr-*.md` dan `docs/quality/refactor-log.md` saat technical defense — brief mensyaratkan kemampuan menjelaskan keputusan desain, bukan sekadar menyerahkan dokumennya.
