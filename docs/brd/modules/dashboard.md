# Modul: Dashboard (`dashboard`)

**Kembali ke**: [00-overview.md](../00-overview.md) · **Terakhir Diperbarui**: 2026-09-04

## Ringkasan

Halaman utama berbasis role yang mengagregasi metrik real-time dari produk, order, dan stok — tanpa angka hardcode.

## Kapabilitas

- **DASHBOARD-CAP-001** — tampilkan metrik ringkasan sesuai role user yang login (total produk, jumlah/daftar low-stock, jumlah SO per status, jumlah PO per status; Sales tambahan melihat jumlah order miliknya sendiri)

## Entitas & Aturan Kunci

- Tidak ada entitas sendiri — murni agregasi baca dari data `product`, `inventory`, `purchase-order`, `sales-order`.

## API Surface

**Expose**:

| Jenis | Nama / Route | Role | File Utama |
| ---- | ------------ | ---- | ---------- |
| HTTP | `GET /dashboard` | Terautentikasi | [`app/Controller/DashboardController.php`](../../app/Controller/DashboardController.php) |

**Konsumsi**:

- `product` — `ProductRepositoryInterface::all`
- `inventory` — `ProductStockRepositoryInterface::countLowStock`/`lowStockList`
- `sales-order` — `SalesOrderRepositoryInterface::countByStatus`/`countSearch`
- `purchase-order` — `PurchaseOrderRepositoryInterface::countSearch`

## Alur Data

- Request `/dashboard` → `DashboardService::summaryFor(role, userId)` melakukan query ke empat repository di atas → merender view sesuai role.

## Screens/Pages

| Screen | Route | File Utama | Kapabilitas Terkait |
| ------ | ----- | ---------- | ------------------ |
| Dashboard | `/dashboard` | [`views/dashboard/index.php`](../../views/dashboard/index.php) | DASHBOARD-CAP-001 |

## Dependensi

- **Modul lain**: `product`, `inventory`, `sales-order`, `purchase-order`

## Cakupan Test

- Tidak ditemukan file test khusus `DashboardService` di `tests/`.

## Gap / Risiko yang Diketahui

- Tidak ada yang spesifik modul ini selain ketergantungan baca terhadap konsistensi keempat modul di atas.

## Change Log

- **2026-09-04**: Versi awal dibuat dari hasil survei kodebase.
