# Modul: Dashboard (`dashboard`)

**Kembali ke**: [00-overview.md](../00-overview.md) · **Terakhir Diperbarui**: 2026-10-06

## Ringkasan

Halaman utama berbasis role yang mengagregasi metrik real-time dari produk, order, dan stok — tanpa angka hardcode.

## Kapabilitas

- **DASHBOARD-CAP-001** — tampilkan metrik ringkasan sesuai role (brief DASH-01), semuanya dari query agregasi:
  - **Admin** (2 baris x 3 kartu): Inventory Value (stok x harga beli), Potential Revenue (stok x harga jual), Potential Margin (selisihnya); Low Stock Products, SO Pending Approval, PO Awaiting Receipt (PO Ordered + Partially Received); panel Low Stock Items.
  - **Sales**: 5 kartu status order miliknya (Draft, Pending Approval, Approved, Fulfilled, Cancelled) dan panel Recent Sales Orders (5 terakhir, dengan empty state). Tanpa Low Stock Items.
  - **Warehouse Staff**: antrean goods receipt (PO Ordered, Partially Received), antrean goods issue (SO Approved), Low Stock Products, dan panel Low Stock Items.
- Nominal uang tampil sebagai Rupiah tanpa desimal (`rupiah()`).

## Entitas & Aturan Kunci

- Tidak ada entitas sendiri — murni agregasi baca dari data `product`, `inventory`, `purchase-order`, `sales-order`.

## API Surface

**Expose**:

| Jenis | Nama / Route | Role | File Utama |
| ---- | ------------ | ---- | ---------- |
| HTTP | `GET /dashboard` | Terautentikasi | [`app/Controller/DashboardController.php`](../../app/Controller/DashboardController.php) |

**Konsumsi**:

- `product` — `ProductRepositoryInterface::all`
- `inventory` — `ProductStockRepositoryInterface::countLowStock`/`lowStockList`/`totalInventoryValue`/`totalRetailValue`
- `sales-order` — `SalesOrderRepositoryInterface::countsByStatus`/`search` (pesanan terbaru milik Sales)
- `purchase-order` — `PurchaseOrderRepositoryInterface::countsByStatus`

## Alur Data

- Request `/dashboard` → `DashboardService::summaryFor(role, userId)` melakukan query ke empat repository di atas → merender view sesuai role.

## Screens/Pages

| Screen | Route | File Utama | Kapabilitas Terkait |
| ------ | ----- | ---------- | ------------------ |
| Dashboard | `/dashboard` | [`views/dashboard/index.php`](../../views/dashboard/index.php) | DASHBOARD-CAP-001 |

## Dependensi

- **Modul lain**: `product`, `inventory`, `sales-order`, `purchase-order`

## Cakupan Test

- `tests/E2E/BriefRequirementsE2ETest.php` memverifikasi dashboard ketiga role terhadap query SQL pembanding: Inventory Value, Potential Revenue/Margin (juga saat stok dinolkan), PO Awaiting Receipt, kartu status Sales, Recent Sales Orders dan empty state, serta antrean Warehouse. `InMemoryRepositoriesTest` menguji `totalRetailValue()`.

## Gap / Risiko yang Diketahui

- Tidak ada yang spesifik modul ini selain ketergantungan baca terhadap konsistensi keempat modul di atas.

## Change Log

- **2026-09-04**: Versi awal dibuat dari hasil survei kodebase.
- **2026-10-06**: Dashboard Admin dirampingkan jadi 2x3 kartu dengan Potential Revenue/Margin dan PO Awaiting Receipt; Sales mendapat Recent Sales Orders dan label status tanpa awalan "My orders"; nominal dalam Rupiah.
