# Tinjauan Keamanan IOMS

Tanggal: 2026-10-03. Cakupan: seluruh kode aplikasi (`app/`, `views/`, `public/`), konfigurasi Docker/Apache/PHP, dan database.

**Metode:** (1) audit baca-kode menyeluruh (XSS, SQL injection, otorisasi per route, sesi, upload, konfigurasi), (2) perbaikan, (3) test otomatis untuk setiap kontrol (unit + E2E lewat HTTP), (4) verifikasi terhadap container Apache sungguhan, (5) scan SonarQube (0 isu, 0 hotspot, security rating A).

## Temuan dan penanganannya

| # | Tingkat | Temuan | Perbaikan | Bukti test |
|---|---|---|---|---|
| H1 | Tinggi | Tidak ada proteksi CSRF; semua aksi POST/PUT/DELETE (approve, fulfill, kelola user) bisa dipicu dari situs lain | Token per-sesi (`Csrf`), disisipkan otomatis ke setiap `<form method="post">` oleh `View::display`, divalidasi di front controller (`Security::rejectForgedRequest`) untuk semua metode selain GET/HEAD/OPTIONS; header `X-CSRF-Token` untuk non-form | `SecurityE2ETest::testStateChangingRequestsWithoutAValidTokenAreRejected`, `…ATokenFromAnotherSessionIsRejected`, `…EveryPostFormCarriesTheToken…`, `CsrfTest` |
| H2 | Tinggi | Sesi tidak divalidasi ulang: user yang dinonaktifkan/dihapus atau diubah role-nya tetap berhak sampai sesi habis; admin bisa menonaktifkan dirinya sendiri | `Auth::refresh()` memuat ulang user dari DB di setiap request (nonaktif/dihapus → logout, role diterapkan seketika); admin tidak bisa menonaktifkan atau menurunkan role akunnya sendiri | `…DeactivatedUserIsSignedOutOnTheNextRequest`, `…DeletedUserLosesAccessImmediately`, `…RoleChangesTakeEffect…`, `…AdminCannotLockThemselvesOut` |
| H3 | Tinggi | Cookie sesi tanpa HttpOnly/SameSite/Secure; tanpa timeout | `HttpOnly`, `SameSite=Lax`, `Secure` otomatis di HTTPS, `use_strict_mode`; idle timeout 30 menit dan batas umur 8 jam (`SESSION_IDLE_TIMEOUT`, `SESSION_ABSOLUTE_TIMEOUT`) | `…SessionCookieIsHttpOnlyAndSameSite`, `SessionHardeningTest` |
| M1 | Sedang | IDOR sales order: Sales bisa membuka dan membatalkan SO milik Sales lain lewat ID | `show` dan `cancel` memeriksa kepemilikan untuk role Sales | `…SeededSalesOrdersRender…OnlyOwnOrdersForSales`, `…SalesCannotCancelAnotherSalesUsersOrder`, `OrderRulesTest` |
| M2 | Sedang | Laporan CSV bocor lintas role (ledger dan ekspor PO terbuka untuk Sales) | Ledger dan ekspor PO hanya Admin/Warehouse; ekspor SO untuk Sales tetap terbatas ke miliknya; parameter `type` di-whitelist | `…ReportsAreRestrictedByRole` |
| M3 | Sedang | Login: tanpa pembatasan percobaan; selisih waktu respons membuka akun mana yang ada | Throttling DB (`login_attempts`): 5 gagal per akun+IP atau 20 per IP dalam 15 menit; `password_verify` selalu dijalankan (hash dummy); password maks 72 byte | `…RepeatedFailedLoginsLockTheAccount…`, `LoginThrottleTest` |
| M4 | Sedang | MySQL terbuka di semua interface; fallback password lemah di compose | Port DB hanya di `127.0.0.1`; fallback password dihapus (`DB_USERNAME`/`DB_PASSWORD` wajib dari `.env`); root MySQL acak | diperiksa di container |
| M5 | Sedang | Logout lewat GET (bisa dipicu `<img>`) | Route GET `/logout` dihapus; logout hanya POST + token | `AuthE2ETest::testLogoutViaPostAndGetEndsTheSession` |
| M6 | Sedang | Order menerima ID customer/supplier/gudang/produk yang tidak ada atau nonaktif (jadi 500), harga negatif, qty ekstrem, tanggal tidak valid | `OrderItemValidator`, `DateRules`, `invalidReferences()` di repository | `…OrdersCannotReferenceMissingOrInactiveRecords…`, `OrderRulesTest` |
| M7 | Sedang | Tanpa header keamanan; versi PHP/Apache terlihat; `display_errors` baru dimatikan setelah autoload | CSP, `X-Frame-Options: DENY`, `nosniff`, `Referrer-Policy`, `Permissions-Policy`, COOP, HSTS di HTTPS; `expose_php=Off`, `ServerTokens Prod`; `display_errors=0` di awal `index.php` | `…ResponsesCarrySecurityHeaders…`; header `Server: Apache` diperiksa di container |
| M8 | Sedang | Injeksi formula spreadsheet di ekspor CSV | Sel teks yang diawali `= + - @ \t \r` diberi apostrof | `…CsvExportNeutralisesSpreadsheetFormulas` |
| L1 | Rendah | Upload hanya dicek lewat `mime_content_type`; proteksi PHP di folder upload tidak aktif untuk PHP 8 dan `uploads/.htaccess` tidak ikut repo | Tambahan `getimagesize`; `uploads/.htaccess` masuk repo; konfigurasi Apache menonaktifkan PHP dan menambah `nosniff` di `uploads` | `…ImageWithAnImageSignatureButNoRealImage…`; file `.php` di uploads → 403 di container |
| L2 | Rendah | `innerHTML` di JS dengan angka dari API | Nilai numerik dipaksa `Number()`; teks tetap di-escape | review kode |
| L3 | Rendah | Wildcard `%`/`_` di pencarian produk tidak di-escape | `addcslashes` pada kata kunci | `…SearchTreatsLikeWildcardsAsPlainText` |
| L4 | Rendah | Image Docker memuat git, unzip, composer, dan bisa menyertakan `vendor/` dev | Build multi-stage; hanya dependency produksi; build gagal keras bila `composer install` gagal | diperiksa di container |

**Tidak ditemukan celah yang bisa dieksploitasi pada:** XSS (semua output di-escape atau berupa cast numerik; teks `<script>` terbukti ter-escape), SQL injection (semua nilai lewat prepared statement; bagian SQL dinamis hanya konstanta dan angka ter-cast), inklusi file, open redirect, dan deserialisasi.

## Kontrol yang aktif

- **Autentikasi:** `password_hash` bcrypt, `session_regenerate_id` saat login, throttling, validasi user per request.
- **Otorisasi:** role dicek di server (`Auth::requireRole`) dan di level objek (kepemilikan SO, larangan menyetujui SO sendiri).
- **Input:** validasi di Service; `OrderItemValidator`; referensi dicek ke DB; upload diverifikasi tipe, ukuran, dan isi gambar.
- **Output:** `e()` untuk HTML, CSP `script-src 'self'`, ekspor CSV dinetralkan.
- **Transport/Container:** HSTS bila HTTPS; container non-root; PHP dimatikan di folder upload; versi server disembunyikan; DB hanya di localhost.

## Cara memverifikasi

```bash
composer coverage                                  # 246 test, termasuk SecurityE2ETest (CSRF, header, lockout, sesi, IDOR, CSV, upload, ...)
curl -sI http://localhost:8080/login               # header keamanan, tanpa X-Powered-By
curl -s -o /dev/null -w "%{http_code}\n" -d 'email=a&password=b' http://localhost:8080/login   # 403 tanpa token CSRF
```

## Risiko yang tersisa (jujur)

- **TLS tidak disediakan oleh compose.** Aplikasi siap HTTPS (cookie `Secure`, HSTS otomatis), tetapi terminasi TLS harus dipasang di depan (reverse proxy). Tanpa HTTPS, kredensial dan cookie berjalan tanpa enkripsi.
- **Akun demo** (`Password123!`) ada di seed untuk keperluan demonstrasi; wajib diganti/dihapus di lingkungan nyata.
- **Throttling hanya untuk login.** Endpoint API belum dibatasi lajunya. Pembatasan berbasis IP memakai `REMOTE_ADDR`; di belakang proxy perlu konfigurasi agar alamat klien yang benar terbaca.
- **Token CSRF per-sesi, bukan per-request**, dan sesi memakai penyimpanan file bawaan PHP (satu server).
- **Harga jual di SO diisi Sales** (dengan validasi tidak negatif), karena diskon memang keputusan bisnis; belum ada batas kewenangan diskon atau persetujuan harga.
- **Tidak ada MFA, audit log perubahan master data, maupun pemindaian malware untuk upload.** Gambar lama tidak dihapus saat diganti.
- **Satu pengecualian SonarQube beralasan:** `php:S2092` (flag `Secure` cookie) ditandai `NOSONAR` di `Session::start`, karena flag diaktifkan otomatis di HTTPS dan harus nonaktif untuk demo HTTP lokal.
- Tinjauan ini bukan pengganti uji penetrasi independen.
