# ADR-004: Kontrol Keamanan Terpusat di Front Controller

- **Status:** Diterima
- **Tanggal:** 2026-10-03

## Konteks

Tinjauan keamanan menemukan tiga celah yang berakar pada tidak adanya kontrol lintas-request: tanpa CSRF, sesi yang tidak divalidasi ulang terhadap database, dan tanpa pembatasan percobaan login. Menambahkan pemeriksaan di tiap controller akan mudah terlewat (ada >60 route dan 25 form) dan sulit diuji.

## Keputusan

1. **CSRF: synchronizer token per-sesi, ditegakkan di satu tempat.** `Security::rejectForgedRequest` dipanggil di `public/index.php` sebelum `Router::dispatch` untuk semua metode selain GET/HEAD/OPTIONS. Token masuk lewat field `_csrf` atau header `X-CSRF-Token`. Field disisipkan otomatis ke setiap `<form method="post">` oleh `View::display`, sehingga view baru tidak bisa lupa. Alternatif double-submit cookie ditolak karena sesi sudah ada; SameSite saja ditolak karena tidak melindungi semua browser dan konteks.
2. **Sesi divalidasi ulang di setiap request.** `Auth::refresh()` memuat user dari database (satu query per request) dan mengikuti status/role terbaru. Biaya satu query dinilai wajar untuk skala ini dibanding risiko hak akses yang basi. Alternatif menyimpan versi sesi atau mencabut sesi lewat tabel ditolak karena lebih kompleks untuk manfaat yang sama.
3. **Throttling login di database**, bukan di sesi: penyerang bisa membuang cookie sesi, sedangkan tabel `login_attempts` (email+IP, jendela 15 menit) tidak bisa dihindari begitu saja. Kunci per akun+IP (5 kegagalan) dan per IP (20) membatasi risiko mengunci akun korban dari alamat lain.
4. **Header keamanan dan cookie sesi diatur di aplikasi** (`Security::sendHeaders`, `Session::start`) dan di konfigurasi image (Apache/PHP), agar tetap berlaku di lingkungan mana pun (server PHP built-in, Apache).
5. **Otorisasi objek** (kepemilikan SO, larangan menonaktifkan akun sendiri) diletakkan di Service/Controller yang memiliki konteks datanya, bukan di lapisan generik.

## Konsekuensi

- Semua request state-changing otomatis terlindungi; test E2E membawa token lewat klien HTTP khusus.
- Satu query tambahan per request untuk user yang login.
- Cookie `Secure` dan HSTS hanya aktif bila request datang lewat HTTPS; TLS harus disediakan di depan aplikasi.
- Tabel baru `login_attempts` perlu ada di database lama (`database/schema.sql` bersifat idempoten: `CREATE TABLE IF NOT EXISTS`).
