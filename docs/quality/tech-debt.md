# Technical Debt — Catatan Jujur

Daftar ini disusun berdasarkan pembacaan langsung kode di `app/` per 2026-09-02 dan diperbarui 2026-10-03 (SonarQube, refactor duplikasi, suite E2E). Tujuannya transparansi, bukan menyembunyikan kekurangan.

## Keterbatasan implementasi saat ini

1. **`ApiController` melewati layer Service.** `ApiController::productAvailability()` membuat instance `MySqlProductRepository`, `MySqlProductStockRepository`, `MySqlWarehouseRepository` secara langsung di dalam Controller, alih-alih memanggil sebuah `ProductAvailabilityService`. Ini menyimpang dari pola layering Controller→Service→Repository yang dipakai konsisten di controller lain, dan membuat logic agregasi (menjumlah stok per gudang) tidak reusable/testable secara terpisah dari HTTP layer.
2. **Validasi input masih sederhana (presence/format dasar).** Validasi di Service (mis. `SalesOrderService::create`, `PurchaseOrderService::create`) memeriksa "wajib diisi", "positif", "tanggal valid Y-m-d", dan keunikan nomor — tapi belum ada validasi lintas-field yang lebih kompleks (mis. memastikan `selling_price` SO tidak di bawah `purchase_price` produk, atau validasi format kontak/telepon di Customer/Supplier).
3. **Rate limiting hanya untuk login.** `AuthController::login` dibatasi lewat `LoginThrottle` (tabel `login_attempts`); endpoint API (`ApiController::productAvailability`) belum dibatasi lajunya, dan pembatasan memakai `REMOTE_ADDR` (di belakang reverse proxy perlu konfigurasi agar IP klien yang benar terbaca).
4. **Otentikasi API memakai session cookie yang sama dengan web** (`Auth::requireLoginApi`), bukan token terpisah (API key/JWT) — cocok untuk kebutuhan internal saat ini, tapi berarti API tidak bisa dipakai oleh klien non-browser tanpa turut menangani cookie session PHP.
5. **Implementasi In-Memory Repository tidak mensimulasikan row-locking MySQL sungguhan.** `InMemoryProductStockRepository::lockForUpdate` (dipakai unit test) tidak benar-benar meniru semantik `SELECT ... FOR UPDATE` InnoDB (blocking antar transaksi konkuren) — sehingga skenario race condition pada `SalesOrderService::fulfill` HANYA benar-benar tervalidasi lewat integration test terhadap MySQL asli (lihat ADR-002), bukan lewat unit test murni.
6. **Integration dan E2E test membutuhkan Docker + MySQL nyata untuk dijalankan reviewer.** Test yang memakai `MySql*Repository` dan seluruh suite E2E tidak bisa jalan tanpa database sungguhan. `composer coverage` (`scripts/coverage.sh`) menyiapkan semuanya otomatis (MySQL sementara + server PHP) dan menjalankan 166 test/903 assertion. Reviewer yang hanya menjalankan `composer test` tanpa env `TEST_DB_*`/`E2E_BASE_URL` akan melihat test tersebut **skip** (bukan gagal), karena sengaja memakai `markTestSkipped()` supaya `composer test` tetap hijau tanpa Docker.
7. **Tidak ada CI/CD pipeline** — sesuai batasan proyek (lihat `docs/planning/scope.md`), tidak ada automated pipeline yang menjalankan test/lint/SonarQube pada setiap push; verifikasi kualitas kode bergantung pada eksekusi manual (`composer coverage`, `phpstan`, `phpcs`, scan SonarQube lokal).
8. **`scripts/check-low-stock.php` adalah skrip CLI murni**, tidak ada scheduler bawaan aplikasi (sesuai larangan cron in-app) — jika reviewer/operator lupa menjadwalkannya via cron OS, notifikasi low stock tidak akan pernah berjalan otomatis.
9. **Upload gambar produk disimpan di filesystem lokal** (`image_path` di tabel `products`). `ProductService` memvalidasi ukuran maksimum (2MB) dan tipe MIME sungguhan (`mime_content_type`, dibatasi ke JPEG/PNG/WEBP) sebelum menyimpan file dengan nama acak (`bin2hex(random_bytes(16))`). Jalur ini sekarang diuji otomatis (PNG/JPEG/WEBP valid, tipe salah, terlalu besar, upload gagal, direktori tidak bisa ditulis) memakai file kecil sintetis; belum diuji dengan foto produk sungguhan berukuran besar.
10. **Tidak ada mekanisme audit trail untuk perubahan master data** (Product, Category, Supplier, Customer, Warehouse) — hanya transaksi stok (PO/SO) yang tercatat di `stock_ledger`. Siapa mengubah harga produk kapan, misalnya, tidak terekam.

11. **Coverage 100% adalah line coverage.** Setiap baris kode aplikasi dieksekusi oleh Unit/Integration/E2E test (lihat ADR-003), tetapi itu tidak menjamin semua kombinasi input atau cabang logika teruji; asersi belum mencakup uji beban/konkurensi paralel sungguhan.
12. **Test E2E mengandalkan data seed demo** (`database/seed.sql`): perubahan ID atau isi seed dapat mematahkan beberapa asersi (mis. `SKU-0001` total stok 56, `SO-2026-0001` milik Sari).

13. **Risiko keamanan yang tersisa** (rinci di `docs/quality/security-review.md`): TLS tidak disediakan compose; akun demo di seed; token CSRF per-sesi; tanpa MFA; harga jual SO diisi Sales tanpa batas kewenangan diskon; gambar lama tidak dihapus saat diganti.

14. **Service bergantung pada `PDO` untuk transaksi.** `SalesOrderService` dan `PurchaseOrderService` menerima `PDO` lewat constructor hanya untuk `beginTransaction/commit/rollBack` (dan meneruskannya ke metode repository yang bertipe `PDO`). Constructor injection terpenuhi dan SQL tetap di Repository, tetapi bentuk yang lebih murni adalah antarmuka `TransactionManager` yang menyembunyikan `PDO` dari Service. Ditunda karena perubahan menyentuh seluruh interface repository dan unit test.
15. **Tidak ada halaman profil sendiri.** Brief menyebut "profil sendiri" pada matriks peran, tetapi tidak ada requirement fungsional untuk halaman profil; user melihat nama dan role di header, dan password hanya diubah oleh Admin.

## Rencana Perbaikan ke Depan

- Refactor `ApiController` agar memanggil `ProductAvailabilityService` (baru) yang menggabungkan tiga Repository tersebut, konsisten dengan Controller lain.
- Menambah rule validasi lintas-field pada `SalesOrderService`/`ProductService` (mis. margin harga minimum) jika dibutuhkan proses bisnis.
- Memperluas rate limiting ke endpoint API dan menambah batas kewenangan diskon harga jual pada SO.
- Menambah kelas `ProductStockLockSimulator` atau helper test khusus agar `InMemoryProductStockRepository` bisa mensimulasikan kontensi (mis. dengan flag "locked" manual) untuk pengujian race condition tanpa MySQL.
- Menyediakan panduan setup Docker+MySQL yang jelas di README agar reviewer bisa menjalankan integration test bila mereka mau (di luar cakupan wajib).
- Menambah audit log sederhana (tabel `audit_log` generik) untuk perubahan master data, jika dibutuhkan kepatuhan/tracing lebih lanjut.
