# ADR-003: Coverage Lewat Test HTTP End-to-End yang Digabung dengan Unit dan Integration Test

- **Status:** Diterima
- **Tanggal:** 2026-10-03

## Konteks

Unit test (memakai `InMemory*Repository`) dan integration test (MySQL asli) menguji Service dan Repository dengan baik, tetapi Controller, view, `public/index.php`, dan sebagian kelas Core tidak pernah dieksekusi oleh keduanya. Hasilnya coverage SonarQube hanya 21%, padahal bagian itulah tempat otorisasi per-role, penanganan error (ERR-01), dan alur form berada.

Controller memanggil `header()`/`http_response_code()` dan bergantung pada session PHP, sehingga menjalankannya langsung di dalam proses PHPUnit rapuh dan akan mendorong perubahan pada kode produksi hanya demi test.

## Keputusan

Menambah suite **E2E** (`tests/E2E`) yang menembak aplikasi sungguhan lewat HTTP (klien cookie-aware berbasis cURL) dan merekam coverage dari server itu sendiri:

1. `scripts/coverage.sh` menyalakan MySQL sementara (Docker, tmpfs) dan server PHP built-in dengan `auto_prepend_file=tests/support/coverage-prepend.php`.
2. File prepend memulai pengumpulan coverage Xdebug per request dan menulis file `.cov` saat shutdown.
3. PHPUnit menjalankan Unit + Integration + E2E, lalu `phpcov merge` menggabungkan semua `.cov` menjadi `clover.xml` (untuk SonarQube) dan laporan HTML.
4. Setiap test E2E mengembalikan database ke schema + seed demo, sehingga test independen dan deterministik.

Cabang yang tidak bisa dicapai lewat HTTP (mis. respons JSON untuk 403/409 di `Controller::handle`, `Auth::requireRoleApi`) diuji sebagai unit test, bukan dikecualikan dari coverage. Kode yang terbukti mustahil dicapai dihapus atau disederhanakan.

## Alternatif yang dipertimbangkan

- **Memanggil Router/Controller di dalam proses PHPUnit.** Ditolak: butuh stub untuk header, session, dan output; menguji lingkungan tiruan, bukan jalur request sebenarnya.
- **Mengecualikan Controller/view dari coverage.** Ditolak: menyembunyikan bagian yang paling banyak berisi aturan akses.
- **Framework browser (Selenium/Playwright).** Ditolak: dependensi berat; tidak ada logika JavaScript sisi klien yang perlu diverifikasi untuk coverage PHP.

## Konsekuensi

- Coverage kini mencerminkan eksekusi nyata dan mencapai 100% line coverage (3.512 baris), dengan perilaku HTTP (status, redirect, pembatasan role) terkunci oleh test.
- Menjalankan `composer coverage` butuh Docker dan PHP dengan Xdebug. `composer test` biasa tetap jalan tanpa keduanya karena suite E2E dilewati bila `E2E_BASE_URL` tidak di-set.
- Itu *line coverage*: tidak menjamin semua kombinasi input teruji. Kualitas asersi tetap perlu direview.
- Suite E2E lebih lambat (≈2 menit) daripada unit test; sebagian waktu itu overhead Xdebug.
