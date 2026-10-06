# Uji Docker dari Kondisi Bersih (brief §5.1, §10)

Dijalankan 2026-10-06 dari `git clone` lokal commit `8a36ba4` ke folder kosong, dengan project Compose
terpisah (`ioms-clean`) agar tidak memakai volume data pengembangan. Prosedur sama dengan README
(Opsi A): `cp .env.example .env` lalu `docker compose up -d --build`.

| Langkah | Hasil |
|---|---|
| Build image dari nol (`docker compose up -d --build`) | Berhasil; `mysql` dan `app` berstatus healthy |
| Schema dan seed otomatis | 32 produk, 25 order (13 PO + 12 SO), 5 user (1 Admin, 2 Sales, 2 WarehouseStaff), 2 gudang |
| Login tiga role | Admin, Sales, Warehouse Staff masuk ke `/dashboard` (200) |
| Halaman Admin | `/products`, `/purchase-orders`, `/sales-orders`, `/reports`, `/users` semuanya 200 |
| Otorisasi | Sales membuka `/users`: 403; tanpa login `/dashboard` diarahkan ke `/login` |
| API JSON | `GET /api/products/SKU-0001/availability`: 200 JSON; SKU tidak ada: 404 JSON; tanpa login: 401 |
| CSV | `stock-ledger.csv` dan `orders.csv?type=purchase` mengembalikan kolom baru (200) |
| Unit test di clone (`composer install` lalu `phpunit --testsuite Unit`) | 157 test, 391 assertion lulus |
| Pembersihan | `docker compose down -v` pada project uji; stack pengembangan dinyalakan lagi |

Test integration dan E2E tidak dijalankan di clone ini; keduanya memakai MySQL sementara milik
`scripts/coverage.sh` dan lulus pada run yang sama hari ini (283 test, coverage 100%, lihat
`docs/testing/test-run-output.txt`).

## Dua mode compose

- `docker compose up --build`: ikut membaca `docker-compose.override.yml`; `app/`, `views/`, `public/` di-mount dari host (dev), `vendor/` dari image. Terverifikasi: mount `/var/www/html/app`, `views`, `public`, `public/uploads`.
- `docker compose -f docker-compose.yml up --build`: persis seperti image, hanya mount `public/uploads`. Terverifikasi: login 200.

Keduanya berjalan dari kondisi bersih; mode kedua dianjurkan untuk demo yang meniru produksi.

## Temuan

- README menyebut container tanpa bind mount padahal override dev aktif otomatis; README diperbarui untuk menjelaskan kedua mode.
- Angka seed pada beberapa dokumen sebelumnya menulis 27 order (diambil dari database pengembangan yang sudah berisi data hasil uji); seed asli adalah 25 order dan sudah dikoreksi.
