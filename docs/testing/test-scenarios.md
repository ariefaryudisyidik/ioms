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
| TS-27 | Laporan | Ekspor CSV stock ledger dengan filter tanggal `date_from`/`date_to` | File CSV terunduh dengan header kolom sesuai `ReportService::stockLedgerCsv`, hanya baris dalam rentang tanggal |
| TS-28 | Laporan | Ekspor CSV status order (`type=po` atau `type=so`) dengan `restrictToUserId` untuk role non-Admin | CSV hanya berisi order milik user tersebut |
| TS-29 | API | `GET /api/products/{sku}/availability` tanpa login (tanpa session valid) | HTTP 401 (`Auth::requireLoginApi`) |
| TS-30 | API | `GET /api/products/{sku}/availability` dengan login valid tapi SKU tidak ditemukan | HTTP 404, body `{"error":"Not Found"}` |
| TS-31 | API | `GET /api/products/{sku}/availability` dengan login valid dan SKU ada di beberapa gudang | HTTP 200, body berisi `sku`, `name`, `total`, dan array `warehouses` dengan quantity per gudang |
| TS-32 | Concurrency (integration, butuh MySQL nyata) | Dua request fulfill SO berbeda menyentuh produk & gudang yang sama secara bersamaan, stok hanya cukup untuk satu | Salah satu transaksi berhasil (stok berkurang benar), yang lain menerima `InsufficientStockException` setelah menunggu lock dilepas — stok akhir tidak pernah negatif |
