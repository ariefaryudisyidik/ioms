# Skenario Test — IOMS

Mencakup alur inti dan edge case. Kolom "Area" mengacu ke Controller/Service asli.

| ID | Area | Langkah | Expected Result |
|---|---|---|---|
| TS-01 | Auth | Login dengan email+password valid milik user aktif | Redirect ke dashboard, session `Auth::user()` terisi sesuai role |
| TS-02 | Auth | Login dengan password salah | Gagal login, flash error ditampilkan, tidak ada session dibuat |
| TS-03 | Auth | Login dengan user yang `is_active = 0` | `AuthService::attempt` mengembalikan `null` meski password benar; login ditolak |
| TS-04 | Auth | Logout saat sedang login | Session dihancurkan (`Session::destroy`), akses halaman terproteksi berikutnya redirect ke `/login` |
| TS-05 | Master Data | Admin membuat produk baru dengan SKU yang sudah ada | `ProductService::create` menolak, error "SKU already exists" (via `ProductRepositoryInterface::skuExists`) |
| TS-06 | Master Data | Admin menghapus kategori yang masih dipakai produk | `CategoryService::delete` menolak via `isUsedByProducts()`, kategori tidak terhapus |
| TS-07 | Master Data | Non-Admin (Sales/WarehouseStaff) mencoba akses halaman manajemen user | Ditolak dengan 403 (`Auth::requireRole`) |
| TS-08 | Purchase Order | Admin membuat PO dengan tanggal order lebih dari toleransi hari ke depan | `PurchaseOrderService::validateOrderDate` mengembalikan pesan error, PO tidak tersimpan |
| TS-09 | Purchase Order | Admin membuat PO dengan minimal 1 item valid | PO tersimpan status `Draft`, item tersimpan dengan `qty_received = 0` |
| TS-10 | Purchase Order | Transisi PO dari `Draft` ke `Ordered` lalu ke `Cancelled` | Transisi pertama sukses; transisi kedua ditolak jika status sudah `Received` (`InvalidStatusTransitionException`) |
| TS-11 | Purchase Order | WarehouseStaff menerima barang sebagian (qty diterima < qty dipesan) | Status PO menjadi `PartiallyReceived`, `qty_received` bertambah, `stock_ledger` mencatat entri `Receipt`, `product_stocks` bertambah sesuai qty diterima (`PurchaseOrderService::receiveGoods`) |
| TS-12 | Purchase Order | Penerimaan barang hingga seluruh item `qty_received = qty_ordered` | Status PO otomatis menjadi `Received` |
| TS-13 | Purchase Order | Menerima barang pada PO berstatus `Received`/`Cancelled` | Ditolak dengan `InvalidStatusTransitionException` |
| TS-14 | Sales Order | Sales membuat SO dengan minimal 1 item valid | SO tersimpan status `Draft`, `so_number` harus unik |
| TS-15 | Sales Order | Sales mengajukan (submit) SO miliknya sendiri | Status berubah `Draft` → `PendingApproval` (`SalesOrderService::submitForApproval`) |
| TS-16 | Sales Order | Sales mengajukan submit SO milik Sales lain | Ditolak `AuthorizationException` ("Only the order owner can submit it for approval.") |
| TS-17 | Sales Order (edge) | Sales (bukan Admin) mencoba approve SO | Ditolak `AuthorizationException` ("Only an Admin can approve sales orders.") — sesuai brief: approve oleh Sales harus ditolak |
| TS-18 | Sales Order (edge) | Admin mencoba approve SO yang dibuatnya sendiri | Ditolak `AuthorizationException` ("An order cannot be approved by its own creator.") |
| TS-19 | Sales Order | Admin (bukan pembuat) approve SO berstatus `PendingApproval` | Status berubah menjadi `Approved`, `approved_by` terisi |
| TS-20 | Sales Order | Admin reject SO berstatus `PendingApproval` | Status kembali ke `Draft` |
| TS-21 | Sales Order (edge) | WarehouseStaff fulfill SO `Approved` dengan stok mencukupi di semua item | Stok berkurang sesuai qty, `stock_ledger` mencatat `Issue`, status jadi `Fulfilled`, transaksi PDO commit |
| TS-22 | Sales Order (edge - insufficient stock) | Fulfill SO dengan salah satu item qty > stok tersedia di gudang | Transaksi rollback total (tidak ada item lain yang terlanjur dikurangi), `InsufficientStockException` dilempar, status SO tetap `Approved` |
| TS-23 | Sales Order | Fulfill SO yang statusnya bukan `Approved` (mis. `Draft`) | Ditolak `InvalidStatusTransitionException` |
| TS-24 | Sales Order | Cancel SO yang statusnya `Fulfilled` | Ditolak `InvalidStatusTransitionException` (SO yang sudah fulfilled tidak bisa dibatalkan) |
| TS-25 | Stok / Low Stock | Jalankan `scripts/check-low-stock.php` saat ada produk dengan `SUM(quantity) < reorder_point` | Skrip melaporkan/mencatat produk tersebut (via `ProductStockRepositoryInterface::lowStockList`) |
| TS-26 | Dashboard | Login sebagai masing-masing role (Admin/Sales/WarehouseStaff), buka dashboard | `DashboardService::summaryFor(role, userId)` menampilkan ringkasan relevan tanpa error, konten disesuaikan role |
| TS-27 | Laporan | Ekspor CSV stock ledger dengan filter tanggal `date_from`/`date_to` | File CSV dengan kolom SKU, Product, Warehouse, Movement Type, Quantity, Reference, Reference Number, Performed By, Date & Time (nama dan nomor order, bukan ID), urut terbaru ke terlama, hanya baris dalam rentang tanggal |
| TS-28 | Laporan | Ekspor CSV status order (`type=purchase` atau `type=sales`); user Sales membatasi ke order miliknya | CSV PO/SO memuat nama pihak terkait, gudang, status berspasi, total qty dan nilai; untuk Sales hanya order miliknya |
| TS-29 | API | `GET /api/products/{sku}/availability` tanpa login (tanpa session valid) | HTTP 401 (`Auth::requireLoginApi`) |
| TS-30 | API | `GET /api/products/{sku}/availability` dengan login valid tapi SKU tidak ditemukan | HTTP 404, body `{"error":"Not Found"}` |
| TS-31 | API | `GET /api/products/{sku}/availability` dengan login valid dan SKU ada di beberapa gudang | HTTP 200, body berisi `sku`, `name`, `total`, dan array `warehouses` dengan quantity per gudang |
| TS-32 | Concurrency (integration, butuh MySQL nyata) | Dua request fulfill SO berbeda menyentuh produk & gudang yang sama secara bersamaan, stok hanya cukup untuk satu | Salah satu transaksi berhasil (stok berkurang benar), yang lain menerima `InsufficientStockException` setelah menunggu lock dilepas — stok akhir tidak pernah negatif |
| TS-33 | Matriks peran (§1.2) | Sales dan Warehouse Staff mencoba membuka form buat produk/kategori/gudang/supplier/customer dan halaman `/users` | 403; produk dan stok tetap bisa dilihat (`BriefRequirementsE2ETest::testMasterDataIsReadOnly…`) |
| TS-34 | Matriks peran | Sales mencoba membuat PO | 403; Admin dan Warehouse Staff bisa |
| TS-35 | Laporan | Sales/Warehouse/Admin mengunduh CSV | Admin semua; Sales hanya order miliknya; Warehouse hanya laporan stok; role lain mendapat 403 (`SecurityE2ETest::testReportsAreRestrictedByRole`) |
| TS-36 | FIND-01 | Cari PO/SO berdasarkan nomor atau nama supplier/customer, filter customer, kombinasi status+sort, pindah ke halaman 2 | Hasil sesuai; filter tetap aktif di link pagination; wildcard `%`/`_` diperlakukan sebagai teks |
| TS-37 | WH-01 | Buka detail produk dengan stok di dua gudang | Rincian per gudang dan total ditampilkan |
| TS-38 | DASH-01 | Buka dashboard sebagai Admin/Sales/Warehouse Staff dan ubah data stok/order | Admin: nilai inventori, low stock, order pending; Sales: order miliknya per status; Warehouse: antrean receipt/issue dan low stock; angka berubah mengikuti data |
| TS-39 | Keamanan | POST tanpa token CSRF, dengan token sesi lain, atau DELETE mentah | 403; data tidak berubah |
| TS-40 | Keamanan | 5 kali login gagal untuk akun yang sama | Akun terkunci sementara walau password benar; akun lain tetap bisa login; sukses login menghapus hitungan |
| TS-41 | Keamanan | User dinonaktifkan/dihapus/diubah role-nya saat sedang login | Request berikutnya logout atau memakai role baru |
| TS-42 | Keamanan | Sales membuka/membatalkan SO milik Sales lain; Admin menyetujui SO sendiri; Admin menonaktifkan akun sendiri | 403 atau ditolak dengan pesan; data tidak berubah |
| TS-43 | Validasi | PO/SO dengan supplier/customer/gudang/produk tidak ada atau nonaktif, harga negatif, qty ekstrem, tanggal tidak valid | Ditolak di backend dengan pesan per field; tidak ada data tersimpan |
| TS-44 | Upload | Gambar dengan tanda tangan PNG tetapi isi bukan gambar, tipe salah, terlalu besar | Ditolak; tidak ada produk tersimpan |
| TS-45 | ERR-01 | Database gagal saat halaman dibuka (tabel tidak ada) | Halaman 500 generik tanpa detail; JSON 500 untuk API |
| TS-46 | JOB-01 | `docker compose exec app php scripts/check-low-stock.php` | Ringkasan produk di bawah reorder point; kode keluar 1 bila database tidak terjangkau |
| TS-47 | ARCH-02 (defense) | Jalankan `scripts/coverage.sh --testdox --filter Integration` (hanya test integration) terhadap MySQL nyata di Docker; hasil run 2026-10-06 untuk `GoodsIssueIntegrationTest` dan `TransactionRollbackIntegrationTest` | 2 test, 10 assertion lulus: goods issue pertama menghabiskan stok (stok 0, SO Fulfilled); goods issue kedua ditolak `InsufficientStockException`, SO kedua tetap Approved, stok tetap 0; kegagalan di tengah transaksi me-rollback semua tulisan sebelumnya |
| TS-48 | UI-01 | Buka login, dashboard tiga role, daftar produk dan SO, detail SO (dengan dialog konfirmasi), form PO, dan Reports pada lebar 360px dan 1280px | Tidak ada elemen terpotong; tabel dashboard muat di 360px; form punya label; dialog konfirmasi fokus awal pada "Go back"; bukti di `docs/testing/screenshots/` |

## Skenario defense ARCH-02 (oversell)

Penjelasan 2 menit untuk technical defense (mekanisme dan alasan lengkap: ADR-002):

1. **Masalah**: dua goods issue untuk produk dan gudang yang sama berjalan bersamaan; keduanya membaca stok 10, lalu keduanya mengurangi 10, sehingga stok menjadi -10 (oversell) atau satu update menimpa yang lain.
2. **Mekanisme**: `SalesOrderService::fulfill()` membungkus semuanya dalam satu transaksi (`TransactionManagerInterface::run`), dan `MySqlProductStockRepository::lockForUpdate()` memakai `SELECT quantity ... FOR UPDATE` pada baris `product_stocks`. Permintaan kedua menunggu sampai yang pertama commit, lalu membaca stok setelah pengurangan.
3. **Bukti**: `GoodsIssueIntegrationTest::testSecondGoodsIssueIsRejectedOnceStockIsExhausted` (hasil TS-47). Request kedua ditolak, SO kedua tetap Approved, stok tetap 0. Atomisitas: `TransactionRollbackIntegrationTest`.
4. **Batas yang jujur**: test memakai dua `fulfill()` berurutan terhadap MySQL nyata, bukan thread paralel sungguhan (diizinkan brief §9 FAQ #8); jaminan konkurensi berasal dari row lock InnoDB.

## Bukti screenshot (UI-01)

Diambil 2026-10-06 dari aplikasi yang berjalan (Chrome headless, data seed). Berkas `*-mobile.png` memakai viewport 360px, `*-desktop.png` 1280px.

| Halaman | Mobile | Desktop |
|---|---|---|
| Login | `screenshots/01-login-mobile.png` | `screenshots/01-login-desktop.png` |
| Dashboard Admin | `screenshots/02-dashboard-admin-mobile.png` | `screenshots/02-dashboard-admin-desktop.png` |
| Dashboard Sales | `screenshots/03-dashboard-sales-mobile.png` | `screenshots/03-dashboard-sales-desktop.png` |
| Dashboard Warehouse | `screenshots/04-dashboard-warehouse-mobile.png` | `screenshots/04-dashboard-warehouse-desktop.png` |
| Daftar produk | `screenshots/05-products-list-mobile.png` | `screenshots/05-products-list-desktop.png` |
| Daftar Sales Order | `screenshots/06-sales-orders-list-mobile.png` | `screenshots/06-sales-orders-list-desktop.png` |
| Detail Sales Order | `screenshots/07-sales-order-detail-mobile.png` | `screenshots/07-sales-order-detail-desktop.png` |
| Dialog konfirmasi | `screenshots/08-confirm-dialog-mobile.png` | `screenshots/08-confirm-dialog-desktop.png` |
| Form PO | `screenshots/09-purchase-order-form-mobile.png` | `screenshots/09-purchase-order-form-desktop.png` |
| Reports | `screenshots/10-reports-mobile.png` | `screenshots/10-reports-desktop.png` |

Temuan saat pengambilan: tabel Low Stock dan Recent Sales Orders di dashboard memotong kolom di 360px karena `min-width: 560px`; diperbaiki dengan varian `table-compact` (tanpa min-width) dan kolom Order Date disembunyikan di layar kecil. Tabel daftar produk dan order tetap bergeser horizontal di dalam kontainernya (`table-wrap`) karena memuat banyak kolom.
