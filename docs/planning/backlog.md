# Backlog Fitur — IOMS

| ID | Fitur | Prioritas | Status |
|---|---|---|---|
| AUTH-01 | Login dengan email + password, session-based | Tinggi | Done — `AuthController::login`, `AuthService::attempt`, `Core/Session` |
| AUTH-02 | Logout & proteksi role-based (Admin/Sales/WarehouseStaff) pada route | Tinggi | Done — `Auth::requireLogin`, `Auth::requireRole`, `Auth::requireRoleApi` |
| USR-01 | CRUD user (create/update/nonaktifkan) + validasi email unik | Tinggi | Done — `UserController`, `UserService`, `UserRepositoryInterface::emailExists` |
| PRD-01 | CRUD produk (SKU unik, kategori, harga, reorder point, upload gambar) | Tinggi | Done — `ProductController`, `ProductService` (menerima `?array $file`) |
| WH-01 | CRUD gudang & stok multi-gudang per produk | Tinggi | Done — `WarehouseController`, `ProductStockRepositoryInterface` |
| PO-01 | Purchase Order: create, transisi status, goods receipt (parsial) | Tinggi | Done — `PurchaseOrderController`, `PurchaseOrderService::create/transitionTo/receiveGoods` |
| SO-01 | Sales Order: create, submit, approve/reject, fulfill (goods issue aman-konkurensi) | Tinggi | Done — `SalesOrderController`, `SalesOrderService` |
| VIEW-01 | Tampilan web (views + layout) untuk semua modul di atas | Tinggi | Done — `views/*`, diverifikasi jalan via Docker |
| FIND-01 | Pencarian & filter/paginasi produk dan order (PO/SO) | Sedang | Done — `ProductService::paginate`, `*RepositoryInterface::search`/`countSearch` |
| DASH-01 | Dashboard ringkasan berbasis role | Sedang | Done — `DashboardService::summaryFor`, `DashboardController` |
| REPORT-01 | Ekspor laporan CSV (stock ledger, status order) dengan filter tanggal | Sedang | Done — `ReportService`, `ReportController` |
| API-01 | REST API cek ketersediaan stok per SKU (`GET /api/products/{sku}/availability`) | Sedang | Done — `ApiController::productAvailability` |
| VAL-01 | Validasi domain di layer Service (required, unique, format tanggal, transisi status valid) | Tinggi | Done — `ValidationException`, `InvalidStatusTransitionException`, `PurchaseOrderService::validateOrderDate` |
| ERR-01 | Penanganan error konsisten (404/401/403/422/500) di Controller & API | Tinggi | Done — `Response::notFound/unauthorized/forbidden`, `Controller::handle` (exception mapping) |
| UI-01 | Layout UI konsisten, form dengan old input & flash message | Sedang | Done — `Session::old/flash/getErrors` + `views/partials/{flash,form-errors}.php`, responsive 360px–desktop |
| DB-01 | Skema database ternormalisasi + seed data demo | Tinggi | Done — `database/schema.sql`, `database/seed.sql` |
| JOB-01 | Skrip pemeriksaan low stock yang dijalankan via cron OS (bukan scheduler in-app) | Rendah | Done — `scripts/check-low-stock.php`, diverifikasi jalan via `docker compose exec app` |

Catatan status: "Done" berarti fitur sudah diverifikasi end-to-end pada tahap verifikasi akhir (2026-09-02) — `docker compose up --build` dari volume bersih, pengujian alur nyata via HTTP request untuk ketiga role, serta full test suite (`composer test`, termasuk integration test terhadap MySQL nyata) dan static analysis (`phpstan`, `phpcs`) hingga 0 error. Lihat `README.md` bagian "Checklist Status Fitur" dan `docs/testing/known-bugs.md` untuk rincian verifikasi.
