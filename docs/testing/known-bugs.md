# Known Bugs — IOMS

Diperbarui 2026-09-02 setelah tahap verifikasi akhir menyeluruh (Docker `up --build` dari volume bersih, pengujian manual seluruh alur via HTTP, full test suite, static analysis).

## Bug yang ditemukan dan sudah diperbaiki selama verifikasi akhir

1. **`Auth::requireRole()` mengembalikan redirect 302 ke `/dashboard`, bukan halaman 403**, saat user yang sudah login mencoba mengakses route yang bukan haknya (mis. Sales membuka `/users`). Ini melanggar ERR-01 ("tanpa kewenangan menampilkan 403"). Diperbaiki di `app/Core/Auth.php`: sekarang merender `views/errors/403.php` dengan status HTTP 403 sungguhan. Diverifikasi ulang via `curl` (Sales → `/users` → `403`, Sales → approve order sendiri → `403`).
2. **False-positive PHPStan** pada `PurchaseOrderService.php`/`SalesOrderService.php` akibat docblock `@param` yang men-declare shape array terlalu ketat (menganggap semua key pasti ada), padahal data berasal dari request mentah yang belum tervalidasi — menyebabkan penggunaan `??` defensif dianggap "always true/redundant" oleh PHPStan. Diperbaiki dengan melonggarkan tipe docblock ke `array<string,mixed>`. Bukan bug fungsional, tapi memperbaiki keakuratan laporan static analysis.
3. **`Core/Env.php:74`** perbandingan `=== null` yang tidak pernah true — dihapus (dead condition, tidak mengubah perilaku).
4. **`Service/ProductService.php`** match-arm `default` yang unreachable menurut PHPStan — saat itu dipertahankan dengan `@phpstan-ignore`; sejak 2026-10-03 diganti peta `EXTENSIONS` sehingga cabang mustahil itu tidak ada lagi (catatan asli: dipertahankan karena berguna bila `ALLOWED_MIME` berubah di masa depan).
5. **4 error PSR-12** di `app/Core/Router.php` dan `app/Controller/Controller.php` — diperbaiki otomatis via `phpcbf`.

Setelah perbaikan di atas: `composer stan` → **0 error**, `phpcs` → **0 error** (sisa 32 warning kosmetik panjang baris, lihat `docs/quality/static-analysis-report.txt`).

## Tidak ditemukan bug fungsional lain

Diverifikasi end-to-end via Docker + HTTP request nyata (bukan hanya membaca kode):
- Login/logout ketiga role, session regenerate, akses halaman terlindungi.
- CRUD master data (produk, kategori, gudang, supplier, customer, user).
- Alur PO: create → goods receipt (penuh & parsial) → status berubah benar, StockLedger tertulis.
- Alur SO: create (Sales) → submit → approve (Admin, ditolak untuk Sales termasuk pemilik order sendiri, 403) → goods issue (Warehouse) → Fulfilled, StockLedger tertulis.
- Race-condition goods-issue: diverifikasi lewat `tests/Integration/GoodsIssueIntegrationTest.php` (permintaan kedua ditolak `InsufficientStockException` saat stok sudah habis oleh permintaan pertama) terhadap MySQL nyata di Docker.
- Dashboard ketiga role, laporan CSV, endpoint `GET /api/products/{sku}/availability` (200 autentikasi valid, 401 tanpa login, 404 SKU tidak ada).
- Script `scripts/check-low-stock.php` dijalankan via `docker compose exec app php scripts/check-low-stock.php` — menghasilkan daftar produk di bawah reorder point yang benar.
- Full test suite: 26 test, 63 assertion, termasuk 4 integration test terhadap MySQL nyata (bukan skip) — semuanya lulus.

## Area yang tetap perlu perhatian manusia sebelum submission

- Validasi keamanan menyeluruh (SQL injection, XSS, CSRF, rate limiting) belum diaudit oleh alat security-scanning khusus — hanya mengikuti praktik minimum wajib brief.
- 32 warning PHPCS "line exceeds 120 characters" masih ada (kosmetik).
- Validasi upload gambar produk (tipe MIME & ukuran) sudah diimplementasikan (`ProductService`) tapi belum diuji dengan file gambar sungguhan berbagai format/ukuran secara manual.
- Lihat `docs/quality/tech-debt.md` untuk keterbatasan desain yang diambil sadar karena keterbatasan waktu.

Jika ditemukan bug fungsional baru pada tahap review berikutnya, perbarui dokumen ini dengan ID bug, langkah reproduksi, dan status perbaikan.

## Pembaruan 2026-10-03

- Full test suite sekarang **316 test / 1883 assertion** (Unit 170, Integration 9, E2E 137 lewat HTTP; run 2026-10-06), semuanya lulus lewat `composer coverage`. Line coverage 100%.
- PHPStan 0 error; PHPCS 0 error dengan 27 warning panjang baris (kosmetik).
- Tidak ada bug fungsional baru ditemukan oleh suite E2E. Satu bug *environment* ditemukan dan diperbaiki lebih awal: `docker-compose.yml` memasang source di atas `vendor/` image sehingga clone bersih langsung fatal error (sudah diperbaiki, bind mount dihapus).

## Pembaruan keamanan 2026-10-03

Tinjauan keamanan menemukan dan memperbaiki celah berikut (rinci di `docs/quality/security-review.md`): tanpa CSRF, sesi tidak divalidasi ulang, cookie sesi tanpa flag, IDOR sales order, kebocoran laporan CSV lintas role, tanpa throttling login, validasi referensi/harga order, formula injection CSV, dan beberapa hardening Docker/Apache. Suite E2E (`SecurityE2ETest`) mengunci perilaku tersebut.
