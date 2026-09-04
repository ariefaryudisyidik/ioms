# Modul: Auth (`auth`)

**Kembali ke**: [00-overview.md](../00-overview.md) · **Terakhir Diperbarui**: 2026-09-04

## Ringkasan

Autentikasi berbasis session dan middleware pengecekan role yang dipakai seluruh modul lain untuk membatasi akses.

## Kapabilitas

- **AUTH-CAP-001** — login dengan email/password, session di-regenerate saat berhasil
- **AUTH-CAP-002** — logout, session dihancurkan
- **AUTH-CAP-003** — membatasi halaman/aksi berdasarkan status login dan/atau role (Admin/Sales/WarehouseStaff), menampilkan halaman 403 sungguhan (bukan redirect) saat gagal otorisasi

## Entitas & Aturan Kunci

- **AUTH-ENT-001 User (session)** — `id`, `name`, `email`, `role` disimpan di `$_SESSION` setelah login (`app/Core/Auth.php`); didukung tabel `users`, lihat [user.md](user.md)
- User nonaktif tidak bisa login (dicek di `AuthService::attempt`, didukung `users.is_active`)

## API Surface

**Expose**:

| Jenis | Nama / Route | Role | File Utama |
| ---- | ------------ | ---- | ---------- |
| HTTP | `GET /login`, `GET /` | Publik | [`app/Controller/AuthController.php`](../../app/Controller/AuthController.php) |
| HTTP | `POST /login` | Publik | `AuthController::login` |
| HTTP | `POST /logout`, `GET /logout` | Terautentikasi | `AuthController::logout` |

**Konsumsi**:

- Modul `user` — `MySqlUserRepository` untuk mencari kredensial
- Seluruh modul lain — `Auth::requireLogin` / `requireRole` / `requireLoginApi` / `requireRoleApi` sebagai guard

## Alur Data

- Login: user submit email/password → `AuthService::attempt` memverifikasi hash + `is_active` → `Auth::login()` regenerate session, simpan data user → redirect `/dashboard`.
- Guard: controller memanggil `Auth::requireRole($roles)` → belum login → redirect `/login`; sudah login tapi role salah → render `errors/403` (HTTP 403).

## Dependensi

- **Modul lain**: `user` (pencarian kredensial)
- **Eksternal**: Session native PHP (`App\Core\Session`), `password_hash`/`password_verify` (bcrypt)

## Cakupan Test

- Tidak ditemukan file test khusus `AuthService`/`Auth` di `tests/Unit/` maupun `tests/Integration/` — perilaku guard role (403 vs redirect) tercatat sudah diverifikasi manual di `docs/testing/known-bugs.md`, bukan lewat automated test.

## Gap / Risiko yang Diketahui

- Tidak ada rate limiting pada `/login` — risiko brute-force (tercatat di `docs/quality/tech-debt.md`).
- Autentikasi API memakai ulang session cookie yang sama dengan web UI; tidak ada skema token terpisah.

## Change Log

- **2026-09-04**: Versi awal dibuat dari hasil survei kodebase.
