# Modul: Customer (`customer`)

**Kembali ke**: [00-overview.md](../00-overview.md) · **Terakhir Diperbarui**: 2026-09-04

## Ringkasan

Master data customer yang menjadi tujuan Sales Order.

## Kapabilitas

- **CUSTOMER-CAP-001** — list customer
- **CUSTOMER-CAP-002** — buat customer (nama, kontak, alamat)
- **CUSTOMER-CAP-003** — edit customer
- **CUSTOMER-CAP-004** — nonaktifkan customer

## Entitas & Aturan Kunci

- **CUSTOMER-ENT-001 Customer** — `name`, `contact`, `address`, `isActive` (`app/Entity/Customer.php`, tabel `customers`); direferensikan oleh `sales_orders.customer_id` (`ON DELETE RESTRICT`)

## API Surface

**Expose**:

| Jenis | Nama / Route | Role | File Utama |
| ---- | ------------ | ---- | ---------- |
| HTTP | `GET/POST /customers*`, `GET/PUT /customers/{id}`, `POST /customers/{id}/deactivate` | Terautentikasi | [`app/Controller/CustomerController.php`](../../app/Controller/CustomerController.php) |

**Konsumsi**: tidak ada (modul master-data leaf)

## Alur Data

- CRUD standar: form submit → validasi `CustomerService` → `MySqlCustomerRepository`.

## Screens/Pages

| Screen | Route | File Utama | Kapabilitas Terkait |
| ------ | ----- | ---------- | ------------------ |
| List customer | `/customers` | [`views/customer/index.php`](../../views/customer/index.php) | CUSTOMER-CAP-001 |
| Create/edit customer | `/customers/create`, `/customers/{id}/edit` | [`views/customer/`](../../views/customer/) | CUSTOMER-CAP-002, 003 |

## Dependensi

- **Modul lain**: direferensikan oleh `sales-order` (FK, bukan pemanggilan)

## Cakupan Test

- Tidak ditemukan file test khusus `CustomerService`/repository di `tests/`.

## Gap / Risiko yang Diketahui

- Tidak ada validasi format kontak/telepon (hanya presence dasar, sesuai `docs/quality/tech-debt.md`).

## Change Log

- **2026-09-04**: Versi awal dibuat dari hasil survei kodebase.
