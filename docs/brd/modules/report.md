# Modul: Report (`report`)

**Kembali ke**: [00-overview.md](../00-overview.md) · **Terakhir Diperbarui**: 2026-10-06

## Ringkasan

Ekspor CSV sinkron untuk pergerakan stock ledger dan status order, dapat difilter berdasarkan rentang tanggal.

## Kapabilitas

- **REPORT-CAP-001** — ekspor pergerakan stock ledger sebagai CSV, difilter rentang tanggal
- **REPORT-CAP-002** — ekspor status order (Purchase atau Sales) sebagai CSV, difilter rentang tanggal (user Sales dibatasi hanya order miliknya sendiri)

## Entitas & Aturan Kunci

- Tidak ada entitas sendiri — membaca data `inventory` (`stock_ledger`), `purchase-order`, dan `sales-order` lewat `ReportRepositoryInterface`, yang sudah me-resolve ID menjadi nama dan nomor order (CSV tidak lagi berisi ID mentah).
- Kolom CSV:
  - **Stock Ledger**: SKU, Product, Warehouse, Movement Type, Quantity, Reference (Purchase Order/Sales Order), Reference Number, Performed By, Date & Time (kolom terakhir, urut terbaru ke terlama).
  - **Purchase Orders**: PO Number, Supplier, Warehouse, Status, Order Date, Total Qty Ordered, Total Qty Received, Total Value, Created By.
  - **Sales Orders**: SO Number, Customer, Warehouse, Status, Order Date, Total Qty, Total Value, Created By, Approved By.
- Status ditulis dengan spasi (`Partially Received`); Total Value = jumlah qty x harga item, angka murni agar bisa dijumlahkan di spreadsheet.
- Hak akses sesuai brief §1.2: Stock Ledger untuk Admin dan Warehouse Staff, PO hanya Admin, SO untuk Admin dan Sales (Sales hanya order miliknya).

## API Surface

**Expose**:

| Jenis | Nama / Route | Role | File Utama |
| ---- | ------------ | ---- | ---------- |
| HTTP | `GET /reports` | Terautentikasi | [`app/Controller/ReportController.php`](../../app/Controller/ReportController.php) |
| HTTP CSV | `GET /reports/stock-ledger.csv` | Terautentikasi | sama, `stockLedgerCsv` |
| HTTP CSV | `GET /reports/orders.csv` | Terautentikasi | sama, `orderStatusCsv` |

**Konsumsi**:

- `ReportRepositoryInterface::stockLedgerRows`, `purchaseOrderRows`, `salesOrderRows` (implementasi `MySqlReportRepository`; `salesOrderRows` difilter `created_by` untuk Sales)

## Alur Data

- Request route `.csv` dengan `date_from`/`date_to` (dan `type=sales|purchase` untuk order) → `ReportService` meminta baris dari `ReportRepositoryInterface` → memformat label dan urutan kolom → menulis CSV lewat `php://temp` (sel teks yang diawali `= + - @` diberi apostrof untuk mencegah formula injection).

## Dependensi

- **Modul lain**: `inventory`, `purchase-order`, `sales-order` (hanya sebagai sumber data tabel; tidak memanggil repository modul tersebut)

## Cakupan Test

- `tests/Unit/ReportServiceTest.php` menguji format CSV dengan mock `ReportRepositoryInterface` (tanpa database); jalur MySQL tercakup oleh `ReportsApiDashboardE2ETest` lewat HTTP (ledger, PO, SO, pembatasan Sales). Line coverage 100%.

## Gap / Risiko yang Diketahui

- Pembuatan CSV bersifat sepenuhnya sinkron dalam satu request HTTP tanpa paginasi/streaming ke client — ekspor volume besar berpotensi lambat; tidak ada background job/queue sesuai keputusan lingkup eksplisit (`docs/planning/scope.md`).

## Change Log

- **2026-09-04**: Versi awal dibuat dari hasil survei kodebase.
- **2026-10-06**: CSV memakai nama dan nomor order (bukan ID), menambah total qty dan nilai, memindahkan Date & Time ke kolom terakhir; query dipindah ke `ReportRepositoryInterface`/`MySqlReportRepository`; menambah `ReportServiceTest`.
