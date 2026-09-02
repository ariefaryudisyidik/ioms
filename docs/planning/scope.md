# Ruang Lingkup Proyek (Scope)

## In-Scope

- Autentikasi berbasis session (PHP native session, `App\Core\Session` + `App\Core\Auth`), tiga role: `Admin`, `Sales`, `WarehouseStaff`.
- CRUD master data: User, Product, Category, Supplier, Customer, Warehouse.
- Manajemen stok multi-gudang per produk (`product_stocks`), dengan reorder point per produk.
- Purchase Order: create → Draft → Ordered → (PartiallyReceived) → Received, atau Cancelled; goods receipt parsial per item.
- Sales Order: create → Draft → PendingApproval → Approved → Fulfilled, atau Cancelled/Draft (reject); approval workflow dengan pemisahan peran (pembuat ≠ penyetuju, penyetuju harus Admin).
- Pencatatan stock ledger (Receipt/Issue/Adjustment) sebagai jejak audit setiap perubahan stok.
- Dashboard ringkasan berbasis role.
- Laporan ekspor CSV (stock ledger, status order) dengan filter tanggal.
- Satu REST endpoint API internal: `GET /api/products/{sku}/availability` (autentikasi via session, bukan token terpisah).
- Validasi domain di layer Service (bukan hanya di form/View), termasuk validasi tanggal order dan transisi status yang sah.
- Concurrency-safety untuk pengurangan stok saat fulfill SO, menggunakan `SELECT ... FOR UPDATE` (pessimistic locking) dalam transaksi PDO.
- Skrip CLI `scripts/check-low-stock.php` yang dijalankan manual (atau dijadwalkan lewat cron **sistem operasi**, bukan built-in scheduler aplikasi) untuk memeriksa produk di bawah reorder point.
- Unit test (PHPUnit) untuk Service/Repository dengan implementasi In-Memory sebagai test double.

## Out-of-Scope (secara eksplisit DILARANG dalam proyek ini)

Sesuai batasan brief, hal-hal berikut **tidak** dibangun/tidak boleh ditambahkan:

- **Microservices** — sistem ini adalah monolit PHP native tunggal.
- **Message queue** (RabbitMQ, Kafka, SQS, dll) — tidak ada pemrosesan asinkron berbasis antrean.
- **Konfigurasi cloud deployment** (Terraform, CloudFormation, Helm chart, dsb) — tidak disediakan.
- **CI/CD pipeline** (GitHub Actions, GitLab CI, Jenkins, dsb) — tidak dibuat sebagai bagian dari proyek ini.
- **Kubernetes** / orkestrasi container tingkat lanjut — hanya Docker/Docker Compose sederhana untuk kebutuhan pengembangan lokal (dikerjakan oleh tim lain, di luar cakupan dokumentasi ini).
- **WebSocket** / komunikasi real-time dua arah — semua interaksi bersifat request-response HTTP biasa.
- **Aplikasi mobile** (native maupun hybrid) — hanya web (server-rendered view).
- **Cron scheduler background yang sungguhan berjalan di dalam aplikasi** (mis. worker daemon PHP) — `check-low-stock.php` adalah skrip CLI yang harus dipicu dari luar (cron OS atau dijalankan manual), bukan proses background yang menjadi bagian dari runtime aplikasi.
- **End-to-end testing dengan Selenium/Playwright** atau automasi browser lain — pengujian dilakukan lewat PHPUnit (unit test) dan skenario manual terdokumentasi, bukan automasi browser.

## Asumsi

- Satu instance database MySQL tunggal; tidak ada replikasi/sharding.
- Semua pengguna berada di satu zona waktu server (tidak ada penanganan timezone per user).
- Password disimpan dengan hashing (`password_hash`/bcrypt terlihat dari format `$2y$10$...` pada seed), bukan dienkripsi/plaintext.
- Upload gambar produk (`image_path`) disimpan di filesystem lokal server (folder `public/uploads`), bukan object storage eksternal (S3, dll).
- Approval SO mengasumsikan hanya ada satu tingkat approval (Admin), tidak ada multi-level approval chain.
- Nomor PO/SO (`po_number`/`so_number`) diinput manual/unik, tidak ada auto-generator format nomor otomatis di layer Service (validasi hanya memastikan keunikan).
- Reviewer memiliki PHP 8.2+, Composer, dan (untuk integration test serta menjalankan aplikasi penuh) Docker + MySQL 8 terpasang.

## Batasan

- Tidak ada rate limiting pada login maupun endpoint API.
- Tidak ada mekanisme refresh token/JWT — otentikasi API memakai session cookie yang sama dengan web (lihat `Auth::requireLoginApi`).
- Validasi input bersifat server-side sederhana (required, format, uniqueness); tidak ada validasi kompleks seperti regex format nomor telepon internasional, dsb.
- Laporan CSV dibangun secara sinkron dalam satu request HTTP — untuk volume data sangat besar berpotensi lambat karena tidak ada background job/queue (lihat larangan message queue di atas).
