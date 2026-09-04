# Modul: Report (`report`)

**Kembali ke**: [00-overview.md](../00-overview.md) · **Terakhir Diperbarui**: 2026-09-04

## Ringkasan

Ekspor CSV sinkron untuk pergerakan stock ledger dan status order, dapat difilter berdasarkan rentang tanggal.

## Kapabilitas

- **REPORT-CAP-001** — ekspor pergerakan stock ledger sebagai CSV, difilter rentang tanggal
- **REPORT-CAP-002** — ekspor status order (Purchase atau Sales) sebagai CSV, difilter rentang tanggal (user Sales dibatasi hanya order miliknya sendiri)

## Entitas & Aturan Kunci

- Tidak ada entitas sendiri — membaca data `inventory` (`stock_ledger`), `purchase-order`, dan `sales-order` apa adanya ke baris CSV.

## API Surface

**Expose**:

| Jenis | Nama / Route | Role | File Utama |
| ---- | ------------ | ---- | ---------- |
| HTTP | `GET /reports` | Terautentikasi | [`app/Controller/ReportController.php`](../../app/Controller/ReportController.php) |
| HTTP CSV | `GET /reports/stock-ledger.csv` | Terautentikasi | sama, `stockLedgerCsv` |
| HTTP CSV | `GET /reports/orders.csv` | Terautentikasi | sama, `orderStatusCsv` |

**Konsumsi**:

- `inventory` — `StockLedgerRepositoryInterface::search`
- `purchase-order` — `PurchaseOrderRepositoryInterface::search`
- `sales-order` — `SalesOrderRepositoryInterface::search` (difilter berdasarkan `created_by` untuk pemanggil non-Admin/dibatasi)

## Alur Data

- Request route `.csv` dengan `date_from`/`date_to` (dan `type=sales|purchase` untuk order) → `ReportService` melakukan query ke repository yang relevan (dibatasi maksimum 100.000 baris) → streaming CSV lewat `php://temp`.

## Dependensi

- **Modul lain**: `inventory`, `purchase-order`, `sales-order`

## Cakupan Test

- Tidak ditemukan file test khusus `ReportService` di `tests/`.

## Gap / Risiko yang Diketahui

- Pembuatan CSV bersifat sepenuhnya sinkron dalam satu request HTTP tanpa paginasi/streaming ke client — ekspor volume besar berpotensi lambat; tidak ada background job/queue sesuai keputusan lingkup eksplisit (`docs/planning/scope.md`).

## Change Log

- **2026-09-04**: Versi awal dibuat dari hasil survei kodebase.
