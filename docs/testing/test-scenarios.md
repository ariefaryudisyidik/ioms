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
