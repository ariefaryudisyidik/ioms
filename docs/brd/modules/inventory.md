# Modul: Inventory (`inventory`)

**Kembali ke**: [00-overview.md](../00-overview.md) · **Terakhir Diperbarui**: 2026-09-04

## Ringkasan

Memegang kuantitas stok per gudang, jejak audit pergerakan stok yang tidak bisa diubah, deteksi low-stock, dan satu-satunya API ketersediaan yang menghadap eksternal.

## Kapabilitas

- **INVENTORY-CAP-001** — lihat stok saat ini per produk per gudang
- **INVENTORY-CAP-002** — deteksi produk di bawah reorder point (total kuantitas di semua gudang < `reorder_point`)
- **INVENTORY-CAP-003** — catat pergerakan stok (Receipt/Issue/Adjustment) sebagai entri audit
- **INVENTORY-CAP-004** — cek ketersediaan produk berdasarkan SKU lewat API
- **INVENTORY-CAP-005** — CLI: laporkan produk low-stock (`scripts/check-low-stock.php`, dijalankan lewat cron OS, bukan scheduler bawaan aplikasi)

## Entitas & Aturan Kunci

- **INVENTORY-ENT-001 ProductStock** — `productId`, `warehouseId`, `quantity` (≥0, DB `CHECK`), unik per (product, warehouse) (`app/Entity/ProductStock.php`, tabel `product_stocks`)
- **INVENTORY-ENT-002 StockLedger** — `productId`, `warehouseId`, `movementType` (Receipt/Issue/Adjustment), `quantity`, `referenceType`+`referenceId` (link polimorfik ke `purchase_order`/`sales_order`), `performedBy` (`app/Entity/StockLedger.php`, tabel `stock_ledger`) — append-only, tidak pernah di-update
- Aturan concurrency: pengurangan stok saat fulfillment SO mengunci baris dengan `SELECT ... FOR UPDATE` di dalam transaksi PDO sebelum memeriksa kecukupan stok (`ProductStockRepositoryInterface::lockForUpdate`, lihat ADR-002)

## API Surface

**Expose**:

| Jenis | Nama / Route | Role | File Utama |
| ---- | ------------ | ---- | ---------- |
| HTTP JSON | `GET /api/products/{sku}/availability` | Terautentikasi (session) | [`app/Controller/ApiController.php`](../../app/Controller/ApiController.php) |
| CLI | `php scripts/check-low-stock.php` | Cron OS / manual | [`scripts/check-low-stock.php`](../../scripts/check-low-stock.php) |

**Konsumsi**:

- Modul `product` — lookup SKU/produk untuk API ketersediaan
- Modul `warehouse` — nama gudang untuk respons API ketersediaan

## Alur Data

- Cek ketersediaan: request `/api/products/{sku}/availability` → `ApiController` mencari produk, lalu baris stok per gudang, menjumlahkan total → respons JSON.
- Perubahan stok: selalu dipicu oleh `purchase-order` (receipt, increment) atau `sales-order` (issue, decrement) — modul ini tidak punya UI create/update langsung; setiap mutasi berasal dari pemanggilan increment/decrement repository oleh dua modul tersebut, sementara `StockLedgerService::search` hanya untuk pembacaan.

## Dependensi

- **Modul lain**: `product`, `warehouse` (lookup); dipanggil oleh `purchase-order` dan `sales-order` untuk setiap mutasi stok
- **Eksternal**: row locking PDO/MySQL (`SELECT ... FOR UPDATE`)

## Cakupan Test

- `tests/Unit/LowStockDetectionTest.php` — tercakup (in-memory)
- `tests/Integration/GoodsIssueIntegrationTest.php`, `tests/Integration/GoodsReceiptIntegrationTest.php`, `tests/Integration/TransactionRollbackIntegrationTest.php` — tercakup terhadap MySQL nyata (butuh Docker; skip jika tidak ada)
- `ApiController::productAvailability` — tidak ditemukan automated test khusus; diverifikasi manual sesuai `docs/testing/known-bugs.md`

## Gap / Risiko yang Diketahui

- `ApiController` membangun tiga repository secara langsung alih-alih lewat `ProductAvailabilityService` — menyimpang dari pola Controller→Service→Repository yang dipakai di tempat lain (`docs/quality/tech-debt.md` item 1).
- `InMemoryProductStockRepository::lockForUpdate` tidak benar-benar mensimulasikan row-locking/blocking MySQL — garansi race-condition hanya divalidasi lewat integration test terhadap MySQL nyata, bukan lewat unit test murni (`docs/quality/tech-debt.md` item 5, ADR-002).
- Tidak ada scheduler bawaan aplikasi untuk cek low-stock, ini keputusan lingkup yang disengaja — bergantung pada cron OS yang dikonfigurasi eksternal, yang bisa saja salah konfigurasi/terlupa.

## Change Log

- **2026-09-04**: Versi awal dibuat dari hasil survei kodebase.
