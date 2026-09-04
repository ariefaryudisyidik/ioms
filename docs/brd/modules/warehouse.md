# Modul: Warehouse (`warehouse`)

**Kembali ke**: [00-overview.md](../00-overview.md) · **Terakhir Diperbarui**: 2026-09-04

## Ringkasan

Master data lokasi gudang fisik, dimensi tempat setiap kuantitas stok dilacak.

## Kapabilitas

- **WAREHOUSE-CAP-001** — list gudang
- **WAREHOUSE-CAP-002** — buat gudang (nama, lokasi)
- **WAREHOUSE-CAP-003** — edit gudang
- **WAREHOUSE-CAP-004** — nonaktifkan gudang

## Entitas & Aturan Kunci

- **WAREHOUSE-ENT-001 Warehouse** — `name`, `location`, `isActive` (`app/Entity/Warehouse.php`, tabel `warehouses`); direferensikan oleh `product_stocks`, `purchase_orders`, `sales_orders` (`ON DELETE RESTRICT`)

## API Surface

**Expose**:

| Jenis | Nama / Route | Role | File Utama |
| ---- | ------------ | ---- | ---------- |
| HTTP | `GET/POST /warehouses*`, `GET/PUT /warehouses/{id}`, `POST /warehouses/{id}/deactivate` | Terautentikasi | [`app/Controller/WarehouseController.php`](../../app/Controller/WarehouseController.php) |

**Konsumsi**: tidak ada (modul master-data leaf)

## Alur Data

- CRUD standar: form submit → validasi `WarehouseService` → `MySqlWarehouseRepository`.

## Screens/Pages

| Screen | Route | File Utama | Kapabilitas Terkait |
| ------ | ----- | ---------- | ------------------ |
| List gudang | `/warehouses` | [`views/warehouse/index.php`](../../views/warehouse/index.php) | WAREHOUSE-CAP-001 |
| Create/edit gudang | `/warehouses/create`, `/warehouses/{id}/edit` | [`views/warehouse/`](../../views/warehouse/) | WAREHOUSE-CAP-002, 003 |

## Dependensi

- **Modul lain**: direferensikan oleh `inventory`, `purchase-order`, `sales-order` (FK, bukan pemanggilan)

## Cakupan Test

- Tidak ditemukan file test khusus `WarehouseService`/repository di `tests/`.

## Gap / Risiko yang Diketahui

- Tidak ada yang spesifik selain gap sistem-wide (lihat overview).

## Change Log

- **2026-09-04**: Versi awal dibuat dari hasil survei kodebase.
