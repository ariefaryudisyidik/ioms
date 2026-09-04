# Modul: User (`user`)

**Kembali ke**: [00-overview.md](../00-overview.md) · **Terakhir Diperbarui**: 2026-09-04

## Ringkasan

Manajemen akun user aplikasi dan penetapan role-nya, khusus Admin.

## Kapabilitas

- **USER-CAP-001** — list user (khusus Admin, diterapkan di controller)
- **USER-CAP-002** — buat user (nama, email, password, role)
- **USER-CAP-003** — edit user
- **USER-CAP-004** — nonaktifkan user

## Entitas & Aturan Kunci

- **USER-ENT-001 User** — `name`, `email` (unik), `passwordHash` (bcrypt via `password_hash`), `role` (enum Admin/Sales/WarehouseStaff), `isActive` (`app/Entity/User.php`, tabel `users`)

## API Surface

**Expose**:

| Jenis | Nama / Route | Role | File Utama |
| ---- | ------------ | ---- | ---------- |
| HTTP | `GET/POST /users*`, `GET/PUT /users/{id}`, `POST /users/{id}/deactivate` | Khusus Admin | [`app/Controller/UserController.php`](../../app/Controller/UserController.php) |

**Konsumsi**: tidak ada secara langsung (modul `auth` membaca `users` untuk login, tapi tidak memanggil modul ini)

## Alur Data

- CRUD standar: form submit → `UserService` memvalidasi (keunikan email, field wajib) → hash password → `MySqlUserRepository`.

## Screens/Pages

| Screen | Route | File Utama | Kapabilitas Terkait |
| ------ | ----- | ---------- | ------------------ |
| List user | `/users` | [`views/user/index.php`](../../views/user/index.php) | USER-CAP-001 |
| Create/edit user | `/users/create`, `/users/{id}/edit` | [`views/user/`](../../views/user/) | USER-CAP-002, 003 |

## Dependensi

- **Modul lain**: dibaca oleh `auth` (lookup login); direferensikan oleh `purchase-order`/`sales-order`/`inventory` sebagai `created_by`/`approved_by`/`performed_by` (hanya FK)
- **Eksternal**: `password_hash`/`password_verify` (bcrypt)

## Cakupan Test

- Tidak ditemukan file test khusus `UserService`/repository di `tests/`.

## Gap / Risiko yang Diketahui

- Tidak ada jejak audit siapa mengubah role/status user dan kapan (gap sistem-wide, lihat overview).

## Change Log

- **2026-09-04**: Versi awal dibuat dari hasil survei kodebase.
