# User Story — IOMS

Format: **Sebagai** [role], **saya ingin** [aksi], **supaya** [tujuan].

## Autentikasi

1. **US-01** Sebagai pengguna (Admin/Sales/WarehouseStaff), saya ingin login menggunakan email dan password, supaya saya bisa mengakses fitur sistem sesuai peran saya. (`AuthController::login`, `AuthService::attempt`)
2. **US-02** Sebagai pengguna yang akunnya dinonaktifkan (`is_active = 0`), saya ingin ditolak saat login, supaya akun yang sudah tidak aktif tidak bisa dipakai masuk sistem. (`AuthService::attempt` memeriksa `is_active`)
3. **US-03** Sebagai pengguna yang sudah login, saya ingin bisa logout, supaya sesi saya berakhir dan perangkat saya aman dari akses tidak sah. (`AuthController::logout`, `Session::destroy`)

## Master Data (Admin)

4. **US-04** Sebagai Admin, saya ingin mengelola akun pengguna (create/update/nonaktifkan), supaya saya bisa mengatur siapa saja yang boleh mengakses sistem dan perannya. (`UserController`, `UserService`)
5. **US-05** Sebagai Admin, saya ingin mengelola data produk (SKU, kategori, harga beli/jual, reorder point, gambar), supaya katalog barang selalu akurat. (`ProductController`, `ProductService`)
6. **US-06** Sebagai Admin, saya ingin mengelola data kategori, supplier, customer, dan gudang, supaya master data pendukung transaksi lengkap dan konsisten. (`CategoryController`, `SupplierController`, `CustomerController`, `WarehouseController`)
7. **US-07** Sebagai Admin, saya ingin mencegah penghapusan kategori/produk yang masih dipakai transaksi, supaya integritas data historis (PO/SO) tidak rusak. (`CategoryRepositoryInterface::isUsedByProducts`, `ProductRepositoryInterface::isUsedInOrders`)

## Purchase Order (Admin/WarehouseStaff)

8. **US-08** Sebagai Admin, saya ingin membuat Purchase Order (PO) baru dengan beberapa item, supaya saya bisa memesan barang ke supplier. (`PurchaseOrderController::store`, `PurchaseOrderService::create`)
9. **US-09** Sebagai Admin, saya ingin sistem menolak tanggal order yang tidak valid atau terlalu jauh di masa depan, supaya data PO tetap wajar. (`PurchaseOrderService::validateOrderDate`)
10. **US-10** Sebagai Admin, saya ingin mengubah status PO dari Draft ke Ordered atau membatalkannya, supaya alur pemesanan mengikuti proses bisnis yang benar. (`PurchaseOrderService::transitionTo`)
11. **US-11** Sebagai WarehouseStaff, saya ingin mencatat penerimaan barang (goods receipt) sebagian atau penuh terhadap PO, supaya stok gudang bertambah sesuai barang yang benar-benar diterima. (`PurchaseOrderService::receiveGoods`, status `PartiallyReceived`/`Received`)

## Sales Order (Sales/Admin/WarehouseStaff)

12. **US-12** Sebagai Sales, saya ingin membuat Sales Order (SO) untuk customer dengan beberapa item, supaya permintaan penjualan tercatat. (`SalesOrderController::store`, `SalesOrderService::create`)
13. **US-13** Sebagai Sales, saya ingin mengajukan SO saya untuk disetujui (submit for approval), supaya order bisa diproses lebih lanjut oleh Admin. (`SalesOrderService::submitForApproval`, hanya pemilik order/`createdBy` yang boleh submit)
14. **US-14** Sebagai Admin, saya ingin menyetujui (approve) atau menolak (reject) SO yang berstatus PendingApproval, supaya hanya order yang valid yang lanjut ke pemenuhan. (`SalesOrderService::approve`/`reject`, role Admin wajib)
15. **US-15** Sebagai Sales, saya ingin sistem menolak saya menyetujui SO yang saya buat sendiri, supaya ada pemisahan tanggung jawab (segregation of duty) antara pembuat dan penyetuju order. (`SalesOrderService::approve` — cek `approverRole !== 'Admin'` dan `createdBy === approverId`)
16. **US-16** Sebagai WarehouseStaff, saya ingin memenuhi (fulfill) SO yang sudah Approved, supaya stok gudang berkurang secara aman meski ada beberapa transaksi bersamaan. (`SalesOrderService::fulfill`, `SELECT ... FOR UPDATE` via `ProductStockRepositoryInterface::lockForUpdate`)
17. **US-17** Sebagai WarehouseStaff, saya ingin sistem menolak fulfill jika stok tidak cukup, supaya stok tidak pernah minus. (`InsufficientStockException`)
18. **US-18** Sebagai Sales/Admin, saya ingin membatalkan SO yang belum difulfill, supaya order yang batal tidak mengganggu proses gudang. (`SalesOrderService::cancel`)

## Stok & Laporan

19. **US-19** Sebagai WarehouseStaff, saya ingin melihat riwayat pergerakan stok (stock ledger) per produk/gudang, supaya saya bisa menelusuri asal setiap perubahan stok (Receipt/Issue/Adjustment). (`StockLedgerService::search`, `StockLedgerRepositoryInterface`)
20. **US-20** Sebagai Admin/WarehouseStaff, saya ingin melihat dashboard ringkasan (jumlah PO/SO per status, produk low stock), supaya saya cepat mengetahui kondisi operasional terkini. (`DashboardService::summaryFor`, `DashboardController`)
21. **US-21** Sebagai Admin, saya ingin sistem memberi tahu produk yang stoknya di bawah reorder point (low stock), supaya saya bisa segera membuat PO baru. (`ProductStockRepositoryInterface::countLowStock`/`lowStockList`, `scripts/check-low-stock.php`)
22. **US-22** Sebagai Admin, saya ingin mengekspor laporan stock ledger dan status order (PO/SO) ke CSV dengan filter tanggal, supaya data bisa diolah lebih lanjut di luar sistem. CSV memuat nama dan nomor order (bukan ID mentah), total qty dan nilai, dan Date & Time di kolom terakhir; laporan stok untuk Admin dan Warehouse Staff, PO hanya Admin, SO untuk Admin dan Sales (Sales hanya order miliknya), sesuai brief §1.2. (`ReportService::stockLedgerCsv`/`orderStatusCsv`, `ReportRepositoryInterface`, `ReportController`)

## API

23. **US-23** Sebagai pihak yang mengonsumsi API internal (mis. aplikasi lain), saya ingin memeriksa ketersediaan stok produk per SKU lewat endpoint `GET /api/products/{sku}/availability`, supaya saya bisa mengecek stok tanpa membuka UI web. (`ApiController::productAvailability`)
24. **US-24** Sebagai pengguna API yang belum login, saya ingin mendapat respons 401 saat memanggil endpoint availability, supaya endpoint tidak bisa diakses tanpa otentikasi. (`Auth::requireLoginApi`)
25. **US-25** Sebagai pengguna API yang login namun memasukkan SKU yang tidak ada, saya ingin mendapat respons 404, supaya saya tahu produk tersebut tidak terdaftar. (`ApiController::productAvailability` — `$product === null` → `json(['error'=>'Not Found'], 404)`)
