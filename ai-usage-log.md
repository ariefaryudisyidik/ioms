# AI Usage Disclosure — IOMS

## Ringkasan

Proyek "Inventory & Order Management System (IOMS)" ini dibangun menggunakan **Claude Code** (Anthropic), sebuah asisten AI berbasis command-line untuk pengembangan perangkat lunak, atas instruksi dari:

- **Pengguna:** arief.aryudisyidik@neuronworks.co.id
- **Tanggal:** 2026-09-02

## Bagian yang Dibangun dengan Bantuan AI

Hampir seluruh codebase proyek ini dihasilkan dengan bantuan Claude Code, mencakup:

- Backend PHP native: `app/Core` (Router, Auth, Session, Database, Request, Response, View, Env), `app/Entity`, `app/Repository` (interface, implementasi MySQL, implementasi In-Memory untuk testing), `app/Service` (termasuk logic bisnis: validasi, state machine status, transaksi PDO, concurrency-safe stock handling), `app/Controller`.
- Skema database (`database/schema.sql`) dan data seed demo (`database/seed.sql`).
- Skrip CLI (`scripts/check-low-stock.php`).
- Seluruh dokumentasi proyek di folder `docs/` (perencanaan, arsitektur, kualitas kode, testing), berkas `README.md`, `phpcs.xml`, `.gitignore`, dan file disclosure ini sendiri.
- Tampilan web (`views/`), aset publik (`public/`), konfigurasi Docker (`Dockerfile`, `docker-compose.yml`), dan test otomatis (`tests/`) dikerjakan secara paralel oleh sesi/agent Claude Code lain sesuai pembagian tugas yang sama.

## Metodologi

Pekerjaan dilakukan secara bertahap: agent backend membangun struktur inti aplikasi (Core, Entity, Repository, Service, Controller, skema DB); agent dokumentasi (yang menulis file ini) kemudian membaca seluruh kode sumber tersebut secara langsung untuk memastikan dokumentasi (user story, ERD, class diagram, ADR, catatan kualitas, skenario test, README) mencerminkan implementasi nyata — bukan asumsi atau karangan — termasuk menjalankan `phpstan` dan `phpcs` untuk laporan analisis statis yang faktual.

## Tanggung Jawab Pengguna

**Pengguna (arief.aryudisyidik@neuronworks.co.id) bertanggung jawab penuh untuk mereview seluruh kode dan dokumentasi dalam proyek ini sebelum submission, deployment, atau penggunaan pada lingkungan produksi.** AI (Claude Code maupun model bahasa apa pun) dapat membuat kesalahan — baik berupa bug logic, kerentanan keamanan yang tidak terdeteksi, asumsi bisnis yang keliru, maupun dokumentasi yang tidak sepenuhnya akurat meski telah diupayakan berdasarkan pembacaan kode nyata. Output AI harus diperlakukan sebagai draft berkualitas tinggi yang tetap memerlukan verifikasi manusia, bukan sebagai kebenaran final yang siap pakai tanpa pengecekan.

Setelah seluruh bagian selesai dibangun secara paralel, dilakukan satu tahap verifikasi akhir menyeluruh: `docker compose up --build` dari volume bersih, pengujian manual alur login/CRUD/PO/SO/approval/goods-issue/segregation-of-duties/API/dashboard/CSV/script low-stock via HTTP request nyata terhadap container, perbaikan 2 bug yang ditemukan saat verifikasi ini (redirect 302 yang seharusnya 403 pada `Auth::requireRole`, dan beberapa temuan PHPStan/PHPCS palsu-positif akibat docblock tipe array yang terlalu ketat), lalu menjalankan ulang seluruh `composer test` (26 test, termasuk 4 integration test terhadap MySQL nyata di Docker — bukan hanya unit test) dan `phpstan`/`phpcs` hingga 0 error.

Beberapa area yang secara eksplisit dicatat masih perlu verifikasi lebih lanjut oleh manusia (lihat juga `docs/quality/tech-debt.md` dan `docs/testing/known-bugs.md`):
- Validasi keamanan menyeluruh (SQL injection, XSS, CSRF, rate limiting) belum diaudit secara khusus oleh alat security-scanning terpisah — hanya mengikuti praktik minimum yang diwajibkan brief (prepared statement, escape output, password hashing, session aman).
- Race condition goods-issue diverifikasi lewat skenario terkontrol (dua `fulfill()` berurutan terhadap MySQL nyata), bukan lewat request paralel/thread sungguhan — sesuai batas yang diizinkan brief.
- 32 warning PHPCS "line exceeds 120 characters" masih tersisa (kosmetik, bukan error) — lihat `docs/quality/static-analysis-report.txt`.
