<!--
Sync Impact Report
- Version change: (unfilled scaffold) -> 1.0.0
- Modified principles: none renamed; all five principle slots filled from the Project Brief
  (docs/report/Project Brief - Programmer.pdf, Edisi 1.0).
- Added sections: Core Principles I-V, "Frontend, API & UX Constraints", "Environment, Data &
  Delivery", Governance.
- Removed sections: none (scaffold example comments replaced by project content).
- Templates requiring updates:
  - .rudis/templates/plan-template.md      ✅ no change needed (Constitution Check is generic and reads this file)
  - .rudis/templates/spec-template.md      ✅ no change needed
  - .rudis/templates/tasks-template.md     ✅ no change needed
  - .claude/commands/rudis.*.md            ✅ no outdated references found
  - README.md                              ✅ already describes the same stack and commands
- Deferred TODOs: none.
-->

# IOMS (Inventory & Order Management System) Constitution

Sumber otoritatif: *Project Brief - Programmer* (Neuronworks, Edisi 1.0, Oktober 2026), disalin di
`docs/report/Project Brief - Programmer.pdf`. Jika constitution ini bertentangan dengan brief, brief
yang menang dan constitution harus diamandemen.

## Core Principles

### I. Backend PHP Native Berlapis dengan Dependency Inversion (NON-NEGOTIABLE)

Backend MUST memakai PHP 8.2+ native, OOP, tanpa framework backend, ORM, atau DI container
framework. Tanggung jawab MUST dipisah menjadi `app/Controller` (HTTP/routing) -> `app/Service`
(business rule) -> `app/Repository` (akses data) dengan `app/Entity` sebagai model; dependency
hanya mengalir dari Controller ke Repository, tidak sebaliknya.

- Service MUST NOT bergantung langsung pada PDO, session, atau superglobal PHP, dan MUST NOT
  membuat `new PDO()` sendiri; dependency masuk lewat constructor injection manual.
- Setiap boundary repository MUST berupa interface. Minimal satu interface MUST punya dua
  implementasi: MySQL asli dan in-memory/fake untuk unit test.
- Business logic MUST dapat diuji tanpa koneksi database.

Rasional: ARCH-01 dan daftar teknologi terlarang pada §4 brief; pelanggaran framework/ORM adalah
critical failure.

### II. Integritas Stok & Transaksi (NON-NEGOTIABLE)

Stok MUST NOT diubah langsung oleh UI atau controller. Setiap perubahan `ProductStock` MUST lewat
service yang menulis baris `StockLedger` (Receipt/Issue/Adjustment) lalu memperbarui `ProductStock`
dalam satu transaksi PDO eksplisit (`beginTransaction`/`commit`/`rollBack`).

- Goods issue dan goods receipt MUST transaksional dan aman dari race condition; dua goods issue
  bersamaan untuk produk dan gudang yang sama MUST NOT menghasilkan stok negatif (oversell) atau
  update yang saling menimpa. Mekanisme (row lock `SELECT ... FOR UPDATE`) MUST didokumentasikan
  di ADR dan dibuktikan lewat test atau skenario terkontrol.
- `ProductStock.quantity >= 0` MUST ditegakkan juga di database (constraint).
- Produk, supplier, dan customer MUST dinonaktifkan, bukan dihapus permanen; produk yang sudah
  dipakai pada order hanya boleh dinonaktifkan.
- Dashboard dan laporan MUST dihitung dari query agregasi atas data nyata, bukan angka statis.

Rasional: ARCH-02, DB-01, dan critical failure "stok diubah tanpa service/ledger" pada §8.2 brief.

### III. Otorisasi di Server & Segregation of Duties

Otorisasi MUST selalu diperiksa di server, bukan hanya disembunyikan lewat UI. Matriks peran pada
§1.2 brief (Admin / Sales / WarehouseStaff) MUST ditegakkan pada authorization layer.

- Sales MUST NOT dapat menyetujui atau menolak Sales Order, termasuk order miliknya sendiri; hanya
  Admin yang boleh approve/reject.
- Sales MUST hanya melihat dan mengekspor order miliknya; WarehouseStaff hanya laporan stok.
- Akses tanpa login MUST diarahkan ke login (302), tanpa kewenangan MUST menampilkan 403, data atau
  URL yang tidak ditemukan MUST menampilkan 404. Stack trace dan exception database MUST NOT
  ditampilkan ke user.
- Tidak ada public registration; semua akun dibuat oleh Admin.

Rasional: AUTH-01/02, USR-01, SO-01, ERR-01; "authorization hanya di frontend" adalah critical
failure.

### IV. Keamanan Minimum

- Password MUST di-hash dengan `password_hash()` dan diverifikasi dengan `password_verify()`;
  plaintext dilarang. Pesan login gagal MUST aman (tidak menjelaskan bagian yang salah) dan user
  tidak aktif MUST NOT dapat login.
- Semua query yang menerima input MUST memakai PDO prepared statement; penggabungan input mentah ke
  SQL dilarang. Output user MUST di-escape sebelum masuk HTML.
- ID session MUST diperbarui setelah login; logout MUST menghapus data autentikasi.
- Secret, credential, token, dan data client/PII MUST NOT masuk repository maupun history; hanya
  `.env.example` yang boleh di-commit.
- Upload gambar MUST divalidasi tipe dan ukurannya dan disimpan dengan nama acak.

Rasional: §4.2 brief dan critical failure keamanan pada §8.2.

### V. Test Terisolasi, Berlapis, dan Static Analysis

Proyek MUST memiliki PHPUnit unit test dan integration test yang dipisah di `tests/Unit` dan
`tests/Integration`, dan seluruhnya MUST hijau pada release final.

- Unit test: minimal 6 test pada minimal 3 area logic; MUST NOT menyentuh session, PDO nyata, atau
  layanan eksternal; test getter/setter trivial tidak dihitung.
- Integration test: minimal 3 test yang menyentuh MySQL nyata di Docker (mis. goods receipt
  menambah stok end-to-end, goods issue kedua ditolak saat stok habis).
- Test MUST mengikuti FIRST: tanpa `sleep()`, tanpa panggilan jaringan nyata, tanpa ketergantungan
  urutan, dan tanpa test yang lulus hanya karena di-skip.
- Static analysis MUST dijalankan (PHPStan level 5+ dan/atau PHP_CodeSniffer PSR-12) dengan nol
  critical error; warning yang tersisa MUST dijelaskan, bukan dibiarkan diam-diam.
- Perubahan perilaku MUST disertai test yang relevan sebelum dianggap selesai.

Rasional: TEST-01/02/03 dan critical failure "tidak ada test valid" pada §8.2 brief.

## Frontend, API & UX Constraints

- Frontend MUST memakai HTML semantik, CSS buatan sendiri, dan Vanilla JavaScript (Fetch API
  diperbolehkan). React, Vue, Angular, jQuery, framework CSS, dan template admin siap pakai
  dilarang. Library icon boleh dipakai hanya bila dicantumkan (lihat README, Asset Pihak Ketiga).
- Aplikasi MUST menyediakan minimal satu endpoint JSON (mis. `GET /api/products/{sku}/availability`)
  dengan autentikasi sama seperti halaman biasa, `Content-Type: application/json`, dan kode status
  tepat (200/401/404), bukan halaman HTML error.
- Validasi MUST dilakukan di frontend dan backend; backend adalah sumber kebenaran. Data MUST NOT
  tersimpan bila validasi gagal dan input yang sudah diisi MUST dipertahankan bila relevan.
- UI-01: halaman login, dashboard, daftar produk/order, detail, dan form MUST dapat dipakai di
  360px dan desktop; navigasi dan tabel tidak terpotong; form punya label; focus state dan kontras
  dasar MUST terlihat.
- Aksi destruktif MUST meminta konfirmasi lewat dialog in-page (elemen `<dialog>` native, Vanilla
  JS, tanpa library); tanpa JS, form tetap terkirim normal.
- Daftar utama MUST berpagination 10 data per halaman dengan filter tetap aktif; keadaan tanpa data
  MUST menampilkan empty state informatif.
- Dashboard MUST sesuai hak akses: Admin (nilai inventori, low stock, order pending), Sales
  (ringkasan order miliknya), WarehouseStaff (antrean receipt/issue dan low stock).

## Environment, Data & Delivery

- Aplikasi dan MySQL 8 MUST dapat dijalankan dengan Docker Compose dari kondisi bersih
  (`docker compose up --build`); konfigurasi lewat environment variable dengan contoh di
  `.env.example`; kode MUST NOT bergantung pada absolute path atau setup khusus komputer
  pengembang.
- Schema (PK, FK, constraint, index relevan) dan seed MUST dapat membuat database dari kondisi
  kosong. Seed minimum: 1 Admin, 2 Sales, 2 WarehouseStaff, 2 gudang, 30 produk dengan variasi
  reorder point dan beberapa low stock, serta 25 order gabungan (PO+SO) dengan variasi status
  termasuk PendingApproval dan Cancelled.
- Script terjadwal (`scripts/check-low-stock.php`) MUST berdiri sendiri dan dapat dijalankan manual
  lewat `docker compose exec`.
- Di luar scope (tidak diwajibkan dan tidak boleh ditambah tanpa alasan kuat): microservices,
  message queue, cloud deployment, CI/CD, Kubernetes, notifikasi real-time, aplikasi mobile, cron
  scheduler otomatis. Over-engineering tanpa alasan jelas dinilai negatif sama seperti kode
  berantakan; setiap pattern atau layer tambahan MUST dibenarkan di Complexity Tracking.
- Artefak desain wajib dan MUST sesuai kode aktual: class diagram initial (`docs/planning/`) dan
  as-built (`docs/architecture/`), 2-3 ADR (`docs/architecture/adr-*.md`), refactoring log (>= 3
  entri) + audit SRP + tech-debt register + critique (`docs/quality/`), serta bukti test dan
  laporan static analysis (`docs/testing/`, `docs/quality/`).
- Git: commit bertahap yang mencerminkan perubahan nyata, minimal satu commit `refactor:` yang
  memperbaiki kode lama (Boy Scout Rule); sumber snippet, package, atau asset pihak ketiga MUST
  dicantumkan.
- Penggunaan AI MUST dicatat di `ai-usage-log.md` (DISCLOSE, REVIEW, VERIFY, TEST); source code
  proprietary, data client, dan credential MUST NOT dikirim ke layanan AI publik. Pengembang tetap
  bertanggung jawab penuh dan harus dapat menjelaskan setiap keputusan arsitektur.

## Governance

Constitution ini mengungguli praktik lain di proyek. Setiap perubahan kode, PR, atau commit MUST
diperiksa terhadap Core Principles dan daftar critical failure pada §8.2 brief; pelanggaran MUST
diperbaiki atau dicatat di Complexity Tracking pada plan beserta alasan dan alternatif yang
ditolak. Constitution Check pada `plan.md` MUST lulus sebelum riset dan diperiksa ulang setelah
desain.

Amandemen MUST didokumentasikan lewat `/rudis.constitution` dengan Sync Impact Report, menyebut
sumber perubahan (mis. jawaban trainer yang dicatat di `docs/planning/`), dan menyelaraskan
template serta dokumen turunan. Versi mengikuti semver: MAJOR untuk penghapusan atau redefinisi
prinsip yang tidak kompatibel, MINOR untuk prinsip atau bagian baru atau perluasan material, PATCH
untuk klarifikasi dan perbaikan redaksi. Panduan operasional harian ada di `README.md`.

**Version**: 1.0.0 | **Ratified**: 2026-10-06 | **Last Amended**: 2026-10-06
