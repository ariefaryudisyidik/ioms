# ADR-005: TransactionManager di Batas Repository, Service Bebas PDO

- **Status:** Diterima
- **Tanggal:** 2026-10-03

## Konteks

Brief (ARCH-01) meminta business logic tidak bergantung langsung pada PDO, session, atau superglobal. Goods issue dan goods receipt harus atomik di beberapa tabel (ARCH-02), jadi Service perlu memulai, meng-commit, dan me-rollback transaksi. Implementasi awal menyuntikkan `PDO` ke Service hanya untuk itu, dan meneruskannya ke metode repository (`lockForUpdate($pdo, ...)`, `record($entry, $pdo)`). Tidak ada `new PDO()` tersembunyi, tetapi detail penyimpanan tetap bocor ke Service dan unit test harus memalsukan `PDO`.

## Keputusan

1. Tambah `TransactionManagerInterface::run(callable $work)` di `app/Repository/` (batas persistence yang sama dengan interface repository). Kerja di dalam callable ter-commit bila selesai dan di-rollback, dengan exception dilempar ulang, bila gagal.
2. `PdoTransactionManager` mengimplementasikannya dengan `beginTransaction/commit/rollBack`. `InMemoryTransactionManager` (test double) menjalankan callable dan mencatat jumlah commit/rollback, sehingga unit test bisa membuktikan perilaku transaksional tanpa database.
3. Hapus parameter `PDO` dari seluruh interface dan implementasi repository. Syaratnya: semua repository yang ikut satu transaksi memakai koneksi yang sama (aplikasi berbagi `Database::connection()`, dan test integration menyuntikkan satu `PDO` ke semuanya). Syarat ini didokumentasikan di `PdoTransactionManager`.
4. Tambah `ArchitectureTest` yang memindai `app/Service` (mengabaikan komentar) dan interface repository agar tidak ada rujukan ke `PDO`, `App\Core\*`, atau superglobal.

## Alternatif yang dipertimbangkan

- **Tetap menyuntikkan `PDO` ke Service** (kondisi awal). Memenuhi bunyi brief, tetapi Service tahu soal teknologi penyimpanan dan test harus memakai `PDO` palsu. Ditolak.
- **Unit-of-Work/ORM penuh.** Berlebihan untuk dua use case transaksional dan dilarang oleh brief (ORM).
- **Transaksi di Repository** (mis. `fulfill()` di dalam repository). Memindahkan business rule ke lapisan data. Ditolak.

## Konsekuensi

- Service murni: hanya bergantung pada interface; unit test goods issue memakai in-memory tanpa `PDO`.
- Semua repository dalam satu transaksi harus berbagi koneksi; menggunakan koneksi berbeda akan diam-diam keluar dari transaksi. Risiko ini dibatasi oleh konstruksi di controller dan dijaga test integration (rollback dan oversell).
- Mekanisme pencegahan oversell (`SELECT ... FOR UPDATE` di dalam `run()`) tidak berubah (ADR-002).
