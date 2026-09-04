# Modul: Supplier (`supplier`)

**Kembali ke**: [00-overview.md](../00-overview.md) · **Terakhir Diperbarui**: 2026-09-04

## Ringkasan

Master data supplier yang menjadi tujuan Purchase Order.

## Kapabilitas

- **SUPPLIER-CAP-001** — list supplier
- **SUPPLIER-CAP-002** — buat supplier (nama, kontak, alamat)
- **SUPPLIER-CAP-003** — edit supplier
- **SUPPLIER-CAP-004** — nonaktifkan supplier

## Entitas & Aturan Kunci

- **SUPPLIER-ENT-001 Supplier** — `name`, `contact`, `address`, `isActive` (`app/Entity/Supplier.php`, tabel `suppliers`); direferensikan oleh `purchase_orders.supplier_id` (`ON DELETE RESTRICT`)

## API Surface

**Expose**:

| Jenis | Nama / Route | Role | File Utama |
| ---- | ------------ | ---- | ---------- |
| HTTP | `GET/POST /suppliers*`, `GET/PUT /suppliers/{id}`, `POST /suppliers/{id}/deactivate` | Terautentikasi | [`app/Controller/SupplierController.php`](../../app/Controller/SupplierController.php) |

**Konsumsi**: tidak ada (modul master-data leaf)

## Alur Data

- CRUD standar: form submit → validasi `SupplierService` → `MySqlSupplierRepository`.

## Screens/Pages

| Screen | Route | File Utama | Kapabilitas Terkait |
| ------ | ----- | ---------- | ------------------ |
| List supplier | `/suppliers` | [`views/supplier/index.php`](../../views/supplier/index.php) | SUPPLIER-CAP-001 |
| Create/edit supplier | `/suppliers/create`, `/suppliers/{id}/edit` | [`views/supplier/`](../../views/supplier/) | SUPPLIER-CAP-002, 003 |

## Dependensi

- **Modul lain**: direferensikan oleh `purchase-order` (FK, bukan pemanggilan)

## Cakupan Test

- Tidak ditemukan file test khusus `SupplierService`/repository di `tests/`.

## Gap / Risiko yang Diketahui

- Tidak ada validasi format kontak/telepon (hanya presence dasar, sesuai `docs/quality/tech-debt.md`).

## Change Log

- **2026-09-04**: Versi awal dibuat dari hasil survei kodebase.
