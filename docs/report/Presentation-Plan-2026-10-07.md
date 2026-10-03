# Rencana Presentasi Final Project: IOMS (Intermediate)

**Rabu, 7 Oktober 2026 · 10 menit presentasi + demo, lalu 10 menit tanya jawab asesor.**
Slide: [`IOMS-Presentation-2026-10-07.pdf`](IOMS-Presentation-2026-10-07.pdf) (12 slide; dibuat ulang dengan `python3 docs/report/slides/build_slides.py`).
Dokumen ini menggantikan skrip lama (`Demo-Script.pdf`, `IOMS-Presentation.pdf`, format 12–15 menit tanpa SonarQube/E2E) yang angkanya sudah basi.

## 0. Persiapan (jangan dilewati)

**H-1 (Selasa):**
- [ ] `git push origin master`; buka repo di jendela incognito, pastikan terakhir ter-push dan bisa diakses.
- [ ] Clone ke folder baru, `cp .env.example .env`, `docker compose up --build`, login: pastikan README benar-benar jalan.
- [ ] Nyalakan SonarQube: `docker compose -f docker-compose.sonar.yml -p ioms-sonar up -d`, buka `http://localhost:9001` (password admin sudah kamu ganti), pastikan proyek `ioms` menunjukkan **Passed, 0 isu, coverage 100%, duplikasi 0%**.
- [ ] `composer coverage` sekali lagi; buka `build/coverage/html/index.html` (laporan coverage per file).
- [ ] Screenshot cadangan: dashboard SonarQube (Overall Code), laporan coverage HTML, output `composer coverage` (237 tests, 903 assertions), `docker compose ps`, demo PO→SO.
- [ ] Latihan dengan timer. Target selesai di 9:30.

**30 menit sebelum:**
- [ ] `docker compose down -v && docker compose up --build -d` (data demo bersih), tunggu `healthy`.
- [ ] Buka tab: `localhost:8080/login`, SonarQube, coverage HTML, `docs/architecture/class-diagram-as-built.md`, editor di `SalesOrderService.php`.
- [ ] Tiga jendela login (admin, sales, warehouse) sudah siap, atau siapkan password `Password123!`.
- [ ] Terminal siap untuk query ledger (lihat §2).

## 1. Garis waktu 10 menit

| Waktu | Bagian | Isi singkat |
|---|---|---|
| 0:00–1:00 | Pembukaan | Nama, masalah, role, status, kontribusi, pengungkapan AI |
| 1:00–5:00 | Demo | Alur A: PO → goods receipt → ledger. Alur B: SO → approval → goods issue + oversell ditolak |
| 5:00–8:00 | Teknis | Layered architecture, transaksi + `FOR UPDATE`, Router/Controller, JSON endpoint, Docker, keamanan |
| 8:00–9:00 | Bukti kualitas | 237 test, SonarQube, coverage, keamanan |
| 9:00–10:00 | Refleksi | Kendala, solusi, keterbatasan, prioritas berikutnya |

### Pembukaan (1 menit): ucapkan kira-kira
> "Saya Arief. IOMS adalah sistem inventori dan order multi-gudang berbasis PHP native tanpa framework. Masalahnya: tim gudang dan sales perlu mencatat pembelian, penjualan, dan stok di beberapa gudang, dengan angka stok yang bisa dipertanggungjawabkan, termasuk saat dua proses berjalan bersamaan. Ada tiga role: Admin, Sales, dan Warehouse Staff. Semua fitur wajib sudah selesai: PO, SO dengan approval, goods receipt dan issue, stock ledger, laporan CSV, dan endpoint JSON. Saya mengerjakan perancangan dan implementasi proyek ini. Saya memakai Claude Code sebagai asisten untuk menulis sebagian besar kode, test, dan dokumentasi, dan saya bertanggung jawab memahami serta menjelaskannya. Rincian ada di `ai-usage-log.md`."

### Demo (4 menit)

Data seed: password semua akun `Password123!`. Admin `admin@ioms.test`, Sales `sari.sales@ioms.test`, Warehouse `rudi.warehouse@ioms.test`.

**Alur A: Purchase Order → penerimaan barang → perubahan data (±2 menit)**
1. Login Warehouse. `/purchase-orders` → Create: supplier, gudang, 1 item (mis. SKU-0001, qty 10) → simpan → status **Draft**.
2. Klik **Order** → **Ordered**. Klik **Receive**, terima sebagian (4) → **PartiallyReceived**.
3. Tunjukkan perubahan data (terminal, §2): stok produk naik 4 dan baris baru `stock_ledger` tipe `Receipt`. Jelaskan: update item, ledger, dan stok terjadi dalam **satu transaksi**.

**Alur B: Sales Order, pemisahan tugas, dan anti-oversell (±2 menit)**
1. Login Sales (Sari). `/sales-orders/create`: pilih **gudang dulu**, lalu produk; kotak *availability* terisi lewat **Fetch API** tanpa reload. Buat SO untuk **SKU-0005** di Gudang Pusat Jakarta (stok hanya 1), qty 5. Submit → **PendingApproval**.
2. Pemisahan tugas: sebagai Sales tombol Approve **tidak tampil** (pesan "Only an Admin can approve…"). Buktikan bahwa server yang menegakkan, bukan UI, dengan `curl` (§2): POST approve memakai session Sales → **403**. Admin juga ditolak bila mencoba menyetujui SO buatannya sendiri (tercakup `SalesOrderE2ETest`).
3. Login Admin → **Approve** → **Approved**. Login Warehouse → **Fulfill** → **ditolak** (stok tidak cukup), pesan error tampil. Tunjukkan stok **tidak berubah** dan tidak ada baris ledger baru (transaksi di-*rollback*).
4. (Jika waktu masih ada) buat SO qty 1 untuk produk ber-stok cukup, fulfill sukses: stok berkurang, ledger tipe `Issue`.

### Implementasi teknis (3 menit): urutan bicara
1. **Alur request → response:** `public/index.php` → `Router::dispatch` → Controller → Service → Repository (interface) → MySQL, lalu `View` (template PHP). Tunjukkan `docs/architecture/class-diagram-as-built.md`.
2. **Tanggung jawab layer:** Controller (HTTP + otorisasi `Auth::requireRole`), Service (aturan bisnis, transaksi), Repository (SQL prepared statement), Entity (data). Service hanya bergantung pada interface (ADR-001), jadi bisa diuji dengan `InMemory*Repository`.
3. **Integritas stok:** buka `SalesOrderService::fulfill` (`app/Service/SalesOrderService.php:206`): `beginTransaction` → `lockForUpdate` (`SELECT … FOR UPDATE`, `MySqlProductStockRepository.php:58`) untuk tiap item → cek stok → decrement + ledger → commit; ada kekurangan → `InsufficientStockException` → `rollBack`. Dua fulfill bersamaan untuk produk yang sama dipaksa antre di row lock (ADR-002).
4. **Keamanan:** token CSRF di semua form, cookie sesi HttpOnly + SameSite + timeout, user divalidasi ulang tiap request, throttling login, role dan kepemilikan objek dicek di server, prepared statement, output di-escape + CSP, upload diverifikasi (`mime` + `getimagesize`), container non-root. Rincian: `docs/quality/security-review.md`, ADR-004.
5. **Frontend & JSON:** Vanilla JS; `public/assets/js/api.js` memakai Fetch ke `GET /api/products/{sku}/availability` (401 tanpa login, 404 SKU tidak ada).
6. **Docker:** `docker compose up --build` → app (Apache, **non-root**, healthcheck) + MySQL (schema dan seed otomatis).

### Bukti kualitas (1 menit)
- Terminal/slide: **237 test, 1.108 assertion** (Unit 124 · Integration 9 · E2E 104 lewat HTTP). Buka laporan coverage HTML.
- SonarQube: **Quality gate Passed · 0 bug/vulnerability/smell/hotspot · coverage 100% · duplikasi 0%**.
- Cerita perbaikan: awal 192 isu dan duplikasi 8,1% → diperbaiki lewat refactor (`CrudController`, `AbstractMySqlRepository`, partial view). Lihat `docs/quality/sonarqube-report.md` dan `refactor-log.md`.

### Refleksi (1 menit)
- **Kendala:** menjaga stok tetap benar saat proses konkuren; clone bersih gagal jalan karena bind mount menimpa `vendor/` (ketahuan saat verifikasi, sudah diperbaiki); coverage Controller/view awalnya 0%.
- **Solusi:** transaksi + row lock; test E2E lewat HTTP dengan coverage yang digabung (ADR-003).
- **Keterbatasan (jujur):** TLS tidak disediakan compose (perlu reverse proxy); rate limiting hanya login; API memakai session cookie; `ApiController` mengakses repository langsung; coverage adalah *line coverage*; tidak ada CI/CD.
- **Prioritas berikutnya:** `ProductAvailabilityService`, rate limiting, audit log master data, token API.

## 2. Perintah bantu saat demo

```bash
# Ledger terbaru
docker compose exec mysql sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" ioms -e "SELECT id,product_id,warehouse_id,movement_type,quantity,reference_type,reference_id FROM stock_ledger ORDER BY id DESC LIMIT 5"'
# Stok satu produk
docker compose exec mysql sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" ioms -e "SELECT p.sku,ps.warehouse_id,ps.quantity FROM product_stocks ps JOIN products p ON p.id=ps.product_id WHERE p.sku=\"SKU-0005\""'
# API
curl -i http://localhost:8080/api/products/SKU-0001/availability   # 401 tanpa session

# Bukti 403 dari server (ganti 13 dengan id SO yang berstatus PendingApproval)
curl -s -c /tmp/sales.jar -o /dev/null -d 'email=sari.sales@ioms.test&password=Password123!' http://localhost:8080/login
curl -i -b /tmp/sales.jar -X POST http://localhost:8080/sales-orders/13/approve | head -1   # HTTP/1.1 403
```

## 3. Antisipasi pertanyaan asesor

| Pertanyaan | Jawaban singkat + bukti |
|---|---|
| Bagaimana mencegah stok negatif / oversell? | Transaksi + `SELECT … FOR UPDATE` per baris stok, cek `available < qty` sebelum decrement, rollback bila gagal. `SalesOrderService::fulfill:206`, ADR-002, `TransactionRollbackIntegrationTest`. |
| Kenapa pessimistic lock, bukan optimistic? | Konflik pada stok panas sering; optimistic butuh retry. Tidak butuh infrastruktur tambahan (mutex). ADR-002. |
| Apa itu stock ledger? | Catatan append-only tiap pergerakan stok (Receipt/Issue) dengan referensi order dan pelaku; dashboard dan laporan dihitung dari situ. |
| Siapa boleh apa? | Admin semua; Sales buat/submit SO miliknya; Warehouse goods receipt/issue dan master produk; approve hanya Admin dan bukan pembuatnya. Dicek di server (`Auth::requireRole`, `SalesOrderService`). |
| Kenapa Service tidak langsung pakai PDO? | Dependency Inversion: bisa diuji tanpa DB, dan SQL terisolasi (ADR-001). |
| Jelaskan alur dari URL sampai halaman. | `index.php` → `Router` (regex + `{param}`) → `Controller` → `Service` → `Repository` → `View::display`. |
| Apa yang berubah di Router hari-hari terakhir? | Argumen handler dipetakan **berdasarkan nama parameter** lewat reflection (`Router::resolveArguments`), jadi handler hanya mendeklarasikan `$request`/`$params` yang dipakai. Ada `RouterTest`. |
| Apa itu `CrudController`? | Template Method untuk 5 controller master data yang alurnya identik (menghapus ≈200 baris duplikat). Subclass hanya set view, URL, role, service. |
| Kenapa coverage 100%, dan apa batasnya? | Unit + Integration + E2E (HTTP) digabung (ADR-003). Itu line coverage, bukan semua kombinasi input. |
| Isu SonarQube apa yang diperbaiki, dan ada yang dikecualikan? | 192 isu → 0. Dua pengecualian beralasan: `php:S2003` (`require_once`) untuk view dan config, karena partial di-include berulang dan `require` config butuh nilai balik; dan `php:S2092` (flag `Secure` cookie sesi) karena flag aktif otomatis di HTTPS dan harus nonaktif untuk demo HTTP lokal. |
| Mengapa gate sempat gagal? | Gate menilai *new code*; setelah refactor besar baseline dipindah ke kondisi bersih (praktik Clean as You Code). Disebut jujur di laporan Sonar. |
| Bagaimana validasi dan keamanan input? | Validasi di Service (wajib, format, unik, angka non-negatif, referensi harus ada dan aktif), prepared statement, escape output + CSP, upload diverifikasi tipe/ukuran/isi gambar. Rate limiting baru untuk login (keterbatasan). |
| Bagaimana melindungi dari CSRF? | Token per-sesi disisipkan otomatis ke setiap form POST oleh `View::display` dan divalidasi di `public/index.php` (`Security::rejectForgedRequest`) sebelum routing; plus cookie `SameSite=Lax`. ADR-004, `SecurityE2ETest`. |
| Bagaimana jika user dinonaktifkan saat sedang login? | `Auth::refresh()` memuat ulang user dari DB di tiap request: nonaktif/dihapus langsung logout, perubahan role langsung berlaku. |
| Bagaimana mencegah brute force login? | `LoginThrottle` + tabel `login_attempts`: 5 gagal per akun+IP atau 20 per IP dalam 15 menit; `password_verify` selalu dijalankan agar waktu respons tidak membocorkan akun. |
| Apa itu IDOR dan apakah ada? | Akses objek milik orang lain lewat ID. Ditemukan di SO (Sales bisa buka/batalkan SO Sales lain), sudah ditutup dan diuji. |
| Apa itu ADR dan mana saja? | Catatan keputusan arsitektur: 001 repository pattern, 002 stok aman konkurensi, 003 strategi E2E/coverage (`docs/architecture/`). |
| Bagian mana yang bantuan AI? | Sebagian besar kode, test, dan dokumentasi dibuat dengan Claude Code; saya mereview, memverifikasi (Docker, test, Sonar), dan bisa menjelaskannya. `ai-usage-log.md`. |
| Apa yang tidak selesai/kurang? | Lihat `docs/quality/tech-debt.md`: rate limiting, token API, audit log, `ApiController` melewati Service, tidak ada CI/CD. |

## 4. Latihan "perubahan kecil" (asesor boleh meminta)

| Permintaan | Di mana mengubah |
|---|---|
| Tambah filter/kolom di daftar produk | `ProductController::index` (filter), `MySqlProductRepository::buildWhere`, `views/product/index.php` |
| Ubah aturan validasi (mis. min. password) | `UserService::validate` (+ ubah `minlength` di `views/user/*.php`) |
| Tambah status/transisi SO | `SalesOrderService::TRANSITIONS` dan `assertTransition` |
| Ubah batas ukuran upload | `ProductService::MAX_BYTES` |
| Tambah role boleh akses halaman | `Auth::requireRole(...)` di controller terkait, atau `WRITE_ROLES` di `CrudController` subclass |
| Jalankan test tertentu | `vendor/bin/phpunit --filter SalesOrderServiceTest`; E2E butuh `composer coverage` |

## 5. Jika demo gagal
Waktu tetap berjalan. Langsung pindah ke screenshot cadangan dan laporan coverage/SonarQube, sambil menjelaskan alur dari kode (`SalesOrderService::fulfill`). Sebutkan bahwa verifikasi bersih sudah dilakukan dari clone baru.
