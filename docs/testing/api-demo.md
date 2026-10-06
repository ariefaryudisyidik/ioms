# Bukti API-01: Demo Endpoint JSON

Brief API-01 meminta bukti berupa **demo pemanggilan endpoint dengan dan tanpa autentikasi, serta SKU tidak
ditemukan**. Endpoint: `GET /api/products/{sku}/availability` (stok per gudang). Kontrak lengkap:
`specs/001-ioms-brief-compliance/contracts/api-availability.md`.

## Cara mendemokan

```bash
docker compose up -d
composer demo:api          # app di http://localhost:8080, login sebagai sari.sales@ioms.test
```

Script login sendiri (token CSRF + akun demo) lalu menampilkan status HTTP, `Content-Type`, dan body untuk
tiga kasus. Pilihan lain lewat environment: `SKU=SKU-0010 composer demo:api`, juga `BASE=...` dan `EMAIL=...`. (`composer demo:api` menjalankan `scripts/demo-api.sh`.)

## Hasil run 2026-10-06 (aplikasi lokal, data seed)

| # | Kasus | Request | Status | Content-Type | Body |
|---|---|---|---|---|---|
| 1 | Tanpa autentikasi | `GET /api/products/SKU-0001/availability` | **401 Unauthorized** | `application/json` | `{"error":"Unauthorized"}` |
| 2 | Dengan autentikasi, SKU ada | `GET /api/products/SKU-0001/availability` | **200 OK** | `application/json` | `{"sku":"SKU-0001","name":"Laptop Asus X441","total":56,"warehouses":[{"warehouse":"Gudang Cabang Surabaya","quantity":9},{"warehouse":"Gudang Pusat Jakarta","quantity":47}]}` |
| 3 | Dengan autentikasi, SKU tidak ditemukan | `GET /api/products/SKU-TIDAK-ADA/availability` | **404 Not Found** | `application/json` | `{"error":"Not Found"}` |

Keluaran mentah `composer demo:api`:

```text
### 1. Tanpa autentikasi (tanpa session)
$ curl -i http://localhost:8080/api/products/SKU-0001/availability
HTTP/1.1 401 Unauthorized
Content-Type: application/json
{"error":"Unauthorized"}

(login sebagai sari.sales@ioms.test)

### 2. Dengan autentikasi, SKU ada (SKU-0001)
$ curl -i http://localhost:8080/api/products/SKU-0001/availability
HTTP/1.1 200 OK
Content-Type: application/json
{"sku":"SKU-0001","name":"Laptop Asus X441","total":56,"warehouses":[{"warehouse":"Gudang Cabang Surabaya","quantity":9},{"warehouse":"Gudang Pusat Jakarta","quantity":47}]}

### 3. Dengan autentikasi, SKU tidak ditemukan
$ curl -i http://localhost:8080/api/products/SKU-TIDAK-ADA/availability
HTTP/1.1 404 Not Found
Content-Type: application/json
{"error":"Not Found"}
```

## Pemenuhan ketentuan minimum API-01

| Ketentuan brief | Terpenuhi oleh |
|---|---|
| Endpoint JSON terpisah dari halaman HTML | Rute `/api/*` memakai `Response::json()`; error `/api/*` juga JSON (`ResponseTest`) |
| Autentikasi diperiksa seperti halaman biasa | `Auth::requireLoginApi()`; kasus 1 (401) |
| `Content-Type: application/json` | Ketiga kasus di atas; diperiksa otomatis pada 401, 200, dan 404 |
| Kode status tepat 200/401/404, bukan halaman HTML error | Kasus 1-3; tidak ada HTML pada respons error |
| Contoh mengembalikan stok per gudang | Kasus 2: `total` dan `warehouses[]` |

## Test otomatis yang sama

| Kasus | Test |
|---|---|
| 401 tanpa login | `AuthE2ETest::testGuestGetsJsonUnauthorizedFromApi` (status dan `application/json`) |
| 200 dan 404 | `ReportsApiDashboardE2ETest::testAvailabilityApiReturnsStockPerWarehouse` (status, `application/json`, isi body) |
| Stok nol | `ReportsApiDashboardE2ETest::testAvailabilityApiShowsUnknownWarehouseAndZeroStockProducts` |
| Logika Service tanpa DB | `ProductAvailabilityServiceTest` (5 test) |
| Tulis tanpa CSRF, DB gagal | `SecurityE2ETest::testForgedApiWritesGetJsonForbidden`, `EmptyStatesAndFailuresE2ETest` (500 JSON generik) |
