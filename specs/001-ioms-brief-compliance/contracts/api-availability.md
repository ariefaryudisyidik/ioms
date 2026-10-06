# Contract: GET /api/products/{sku}/availability

Satu-satunya endpoint JSON (brief API-01). Diimplementasikan oleh `ApiController::productAvailability`
dan `ProductAvailabilityService`.

- **Authentication**: wajib session login yang sama seperti halaman biasa.
- **Authorization**: semua role yang login boleh membaca (Admin, Sales, WarehouseStaff); data hanya baca.
- **Content-Type**: `application/json`.

| Status | Kondisi | Body |
| ------ | ------- | ---- |
| 200 | SKU ditemukan | `{"sku","name","total","warehouses":[{"warehouse","quantity"}]}` |
| 401 | Tanpa login | `{"error":"..."}` (bukan halaman HTML) |
| 404 | SKU tidak ditemukan | `{"error":"..."}` |

Urutan pemeriksaan: autentikasi -> validasi input -> logika bisnis -> keluaran JSON. Respons error
seragam dan tidak membocorkan detail internal. Bukti: `ReportsApiDashboardE2ETest::testAvailabilityApi*`.
