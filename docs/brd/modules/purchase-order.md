# Modul: Purchase Order (`purchase-order`)

**Kembali ke**: [00-overview.md](../00-overview.md) · **Terakhir Diperbarui**: 2026-09-04

## Ringkasan

Mengelola siklus pengadaan ke supplier dari draft order hingga penerimaan barang (parsial), menambah stok gudang saat barang tiba.

## Kapabilitas

- **PO-CAP-001** — buat draft PO dengan supplier, gudang, tanggal order, item (produk + qty + harga beli)
- **PO-CAP-002** — transisi Draft → Ordered
- **PO-CAP-003** — batalkan PO (di status non-final apa pun kecuali Received)
- **PO-CAP-004** — terima barang untuk PO berstatus Ordered/PartiallyReceived, parsial per item, otomatis menghitung status PartiallyReceived vs Received
- **PO-CAP-005** — list/lihat PO

## Entitas & Aturan Kunci

- **PO-ENT-001 PurchaseOrder** — `poNumber` (unik), `supplierId`, `warehouseId`, `status` (Draft/Ordered/PartiallyReceived/Received/Cancelled), `orderDate`, `createdBy` (`app/Entity/PurchaseOrder.php`, tabel `purchase_orders`)
- **PO-ENT-002 PurchaseOrderItem** — `purchaseOrderId`, `productId`, `qtyOrdered`, `qtyReceived` (≤ qtyOrdered), `purchasePrice` (`app/Entity/PurchaseOrderItem.php`, tabel `purchase_order_items`)
- Transisi status diterapkan di `PurchaseOrderService::assertTransitionAllowed` (`app/Service/PurchaseOrderService.php`); Cancelled bisa dicapai dari status apa pun kecuali Received/Cancelled itu sendiri
- `order_date` harus tanggal valid format `Y-m-d` dan tidak lebih jauh ke depan dari toleransi yang dikonfigurasi (default 0 hari)

## API Surface

**Expose**:

| Jenis | Nama / Route | Role | File Utama |
| ---- | ------------ | ---- | ---------- |
| HTTP | `GET/POST /purchase-orders*`, `GET /purchase-orders/{id}` | Terautentikasi | [`app/Controller/PurchaseOrderController.php`](../../app/Controller/PurchaseOrderController.php) |
| HTTP | `POST /purchase-orders/{id}/order`, `/cancel` | Terautentikasi | sama |
| HTTP | `GET /purchase-orders/{id}/receive`, `POST .../receive` | Terautentikasi (umumnya WarehouseStaff) | sama |

**Konsumsi**:

- Modul `supplier`, `warehouse`, `product` — data referensi untuk PO/item
- Modul `inventory` — `StockLedgerRepositoryInterface::record` (entri Receipt) dan `ProductStockRepositoryInterface::increment` saat penerimaan barang

## Alur Data

- Create: user submit PO + item → `PurchaseOrderService::create` memvalidasi (keunikan nomor PO, supplier/gudang wajib, tanggal, ≥1 item dengan qty positif) → tersimpan sebagai Draft.
- Terima barang (satu transaksi PDO): untuk setiap item yang diterima → clamp ke sisa qty → update `qty_received` → tulis entri Receipt di `stock_ledger` → increment `product_stocks` → setelah semua item, hitung ulang status PO (Received jika semua item diterima penuh, selain itu PartiallyReceived) → commit; jika ada kegagalan seluruh transaksi rollback (`PurchaseOrderService::receiveGoods`).

## Screens/Pages

| Screen | Route | File Utama | Kapabilitas Terkait |
| ------ | ----- | ---------- | ------------------ |
| List PO | `/purchase-orders` | [`views/purchase_order/index.php`](../../views/purchase_order/index.php) | PO-CAP-005 |
| Detail PO | `/purchase-orders/{id}` | [`views/purchase_order/show.php`](../../views/purchase_order/show.php) | PO-CAP-005 |
| Create PO | `/purchase-orders/create` | [`views/purchase_order/create.php`](../../views/purchase_order/create.php) | PO-CAP-001 |
| Form penerimaan barang | `/purchase-orders/{id}/receive` | [`views/purchase_order/receive.php`](../../views/purchase_order/receive.php) | PO-CAP-004 |

## Dependensi

- **Modul lain**: `supplier`, `warehouse`, `product`, `inventory`
- **Eksternal**: transaksi PDO (`beginTransaction`/`commit`/`rollBack`)

## Cakupan Test

- `tests/Unit/PurchaseOrderDateValidationTest.php` — mencakup `validateOrderDate`
- `tests/Integration/GoodsReceiptIntegrationTest.php`, `tests/Integration/TransactionRollbackIntegrationTest.php` — mencakup `receiveGoods` terhadap MySQL nyata
- Tidak ditemukan unit test untuk cabang validasi `create()` atau matriks transisi status secara spesifik

## Gap / Risiko yang Diketahui

- Tidak ada format nomor PO otomatis — input manual, hanya keunikan yang divalidasi (asumsi terdokumentasi di `docs/planning/scope.md`).

## Change Log

- **2026-09-04**: Versi awal dibuat dari hasil survei kodebase.
