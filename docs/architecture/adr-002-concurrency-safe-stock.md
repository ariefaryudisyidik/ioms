# ADR-002: Pessimistic Row Lock (SELECT ... FOR UPDATE) untuk Goods Issue yang Aman-Konkurensi

## Status
Diterima (Accepted).

## Context

Saat Sales Order difulfill (`SalesOrderService::fulfill`), sistem harus mengurangi kuantitas di `product_stocks` untuk setiap item SO. Jika dua SO untuk produk dan gudang yang sama difulfill secara bersamaan (dua request HTTP paralel), tanpa proteksi keduanya bisa membaca nilai stok "available" yang sama sebelum salah satunya menulis — menghasilkan **race condition** yang membuat stok akhir salah (bisa menjadi negatif, melanggar `CHECK (quantity >= 0)` di `product_stocks`, atau lolos padahal stok sebenarnya tidak cukup).

Alternatif yang dipertimbangkan:

1. **Optimistic locking** (kolom `version`/`updated_at` dicek saat UPDATE, retry jika conflict) — tidak memerlukan lock DB aktif, cocok untuk kontensi rendah. Namun butuh logic retry di layer Service, dan pada kontensi tinggi (banyak SO memperebutkan produk yang sama, mis. saat stok menipis) akan menghasilkan banyak retry/gagal berulang, memperumit UX ("coba lagi") dan menambah kompleksitas kode.
2. **Application-level mutex** (mis. lock berbasis file, named lock di luar DB, atau tabel lock manual) — menambah dependency/infrastruktur baru (dan berpotensi melanggar batasan proyek yang melarang komponen infrastruktur tambahan seperti message queue/koordinasi terdistribusi), serta tidak otomatis dilepas jika proses PHP crash di tengah jalan tanpa penanganan timeout yang hati-hati.
3. **Pessimistic row lock** (`SELECT ... FOR UPDATE` dalam transaksi InnoDB) — mengandalkan mekanisme locking bawaan MySQL/InnoDB yang sudah teruji, transaksi otomatis melepas lock saat commit/rollback, cocok untuk kontensi sedang seperti kasus IOMS (goods issue bukan operasi high-throughput ribuan TPS).

## Decision

Menggunakan **pessimistic row lock** lewat `SELECT ... FOR UPDATE`, diimplementasikan di:

- `App\Repository\MySqlProductStockRepository::lockForUpdate(PDO $pdo, int $productId, int $warehouseId): int` — memastikan baris `product_stocks` untuk kombinasi produk+gudang tersebut ada (`INSERT ... ON DUPLICATE KEY UPDATE product_id = product_id` agar baris bisa dikunci secara deterministik walau qty masih 0), lalu menjalankan `SELECT quantity FROM product_stocks WHERE product_id = ? AND warehouse_id = ? FOR UPDATE` dan mengembalikan kuantitas yang terkunci.
- `App\Service\SalesOrderService::fulfill(int $soId, int $userId)` — membungkus seluruh proses dalam satu transaksi PDO (`$this->pdo->beginTransaction()` ... `commit()`/`rollBack()`):
  1. Untuk setiap item SO, panggil `lockForUpdate()` untuk mengunci baris stok terkait dan membaca `$available`.
  2. Jika `$available < $item->qty`, lempar `InsufficientStockException::forProduct(...)` — transaksi otomatis di-`rollBack()` di blok `catch (Throwable $e)`, tidak ada perubahan stok yang tertulis.
  3. Jika semua item lolos verifikasi, baru dijalankan `decrement()` per item dan pencatatan `StockLedger` (`movementType: StockLedger::TYPE_ISSUE`), lalu `updateStatus(... STATUS_FULFILLED ...)`, dan `commit()`.

Karena lock diambil untuk **semua item lebih dulu** sebelum ada decrement yang ditulis, transaksi lain yang mencoba fulfill SO berbeda namun menyentuh produk/gudang yang sama akan diblokir oleh InnoDB pada baris yang sama hingga transaksi pertama commit/rollback — mencegah dua transaksi membaca stok "available" yang sama secara bersamaan (mencegah oversell).

## Consequences

**Positif:**
- Stok tidak pernah menjadi negatif secara logis (diperkuat pula oleh `CHECK (quantity >= 0)` di skema sebagai lapisan pertahanan kedua).
- Tidak perlu logic retry di level aplikasi — MySQL yang menangani antrean lock.
- Konsisten dengan pola yang sama dipakai untuk goods receipt (`PurchaseOrderService::receiveGoods`), yang juga membungkus update `qty_received`, `product_stocks`, dan `stock_ledger` dalam satu transaksi PDO (meski `receiveGoods` menambah stok/`increment`, sehingga risiko race condition lebih rendah karena tidak ada batas bawah yang bisa dilanggar — namun tetap transaksional demi konsistensi ledger vs stok).

**Negatif / trade-off:**
- Row lock menahan koneksi DB lebih lama selama transaksi berjalan; pada beban sangat tinggi dengan banyak SO memperebutkan produk populer yang sama, bisa terjadi antrean lock (lock wait), dan berpotensi `Lock wait timeout` jika transaksi lain macet — namun untuk skala IOMS (aplikasi internal, bukan e-commerce publik), ini dianggap dapat diterima.
- Pendekatan ini spesifik terhadap MySQL/InnoDB (`FOR UPDATE`); implementasi In-Memory (`InMemoryProductStockRepository`, dipakai di unit test) tidak benar-benar mensimulasikan row locking — konsekuensinya, uji konkurensi race-condition sungguhan hanya bisa divalidasi lewat integration test terhadap MySQL asli, bukan lewat unit test murni (lihat `docs/quality/tech-debt.md` dan `docs/testing/known-bugs.md`).
