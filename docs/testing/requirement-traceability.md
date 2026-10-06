# Keterlacakan Requirement Brief -> Kode, Test, Dokumen

Diperbarui 2026-10-06. Sumber: `docs/report/Project Brief - Programmer.pdf`. Semua berkas pada tabel
sudah dicek keberadaannya. Run test terbaru: 283 test, 1329 assertion, line coverage 100%
(`composer coverage`); PHPStan level 5 nol error; PHPCS PSR-12 nol error.

| ID brief | Kode utama | Test | Dokumen |
|---|---|---|---|
| AUTH-01 Login & session | `app/Service/AuthService.php`, `app/Core/Auth.php`, `app/Controller/AuthController.php` | `tests/E2E/AuthE2ETest.php`, `tests/Unit/AuthRoleApiTest.php` | `docs/quality/security-review.md`, ADR-004 |
| AUTH-02 Logout | `app/Controller/AuthController.php` | `tests/E2E/AuthE2ETest.php` | `docs/testing/test-scenarios.md` |
| USR-01 Manajemen user | `app/Service/UserService.php`, `app/Controller/UserController.php` | `tests/E2E/CrudResourcesE2ETest.php` | `docs/brd/modules/user.md` |
| PRD-01 Produk, reorder point, upload | `app/Service/ProductService.php` | `tests/E2E/ProductE2ETest.php`, `tests/Unit/ProductServiceTest.php` | `docs/brd/modules/product.md` |
| WH-01 Gudang & stok multi-lokasi | `app/Service/WarehouseService.php`, `views/product/show.php` | `tests/E2E/BriefRequirementsE2ETest.php` | `docs/architecture/database-design.md` |
| PO-01 PO & goods receipt | `app/Service/PurchaseOrderService.php` | `tests/Integration/GoodsReceiptIntegrationTest.php`, `tests/Unit/PurchaseOrderReceiptValidationTest.php` | ADR-005 |
| SO-01 SO, approval, goods issue | `app/Service/SalesOrderService.php` | `tests/Integration/GoodsIssueIntegrationTest.php`, `tests/Unit/SalesOrderServiceTest.php`, `tests/E2E/SalesOrderE2ETest.php` | ADR-002 |
| VIEW-01 Daftar, detail, empty state | `views/*` | `tests/E2E/EmptyStatesAndFailuresE2ETest.php` | `docs/testing/test-scenarios.md` |
| FIND-01 Search/filter/sort/pagination | `app/Repository/AbstractMySqlRepository.php` (`buildWhere`, `searchRows`) | `tests/E2E/ProductE2ETest.php`, `tests/E2E/PurchaseOrderE2ETest.php` | `database/seed.sql` (32 produk, 25 order: 13 PO + 12 SO; terverifikasi dari clone bersih) |
| DASH-01 Dashboard per role | `app/Service/DashboardService.php`, `views/dashboard/index.php` | `tests/E2E/BriefRequirementsE2ETest.php` | `docs/brd/modules/dashboard.md` |
| REPORT-01 Laporan CSV | `app/Service/ReportService.php`, `app/Repository/MySqlReportRepository.php` | `tests/Unit/ReportServiceTest.php`, `tests/E2E/ReportsApiDashboardE2ETest.php` | `docs/brd/modules/report.md` |
| API-01 Endpoint JSON | `app/Controller/ApiController.php`, `app/Service/ProductAvailabilityService.php` | `tests/E2E/ReportsApiDashboardE2ETest.php` | `specs/001-ioms-brief-compliance/contracts/api-availability.md` |
| VAL-01 Validasi FE + BE | `public/assets/js/validation.js`, `app/Service/OrderItemValidator.php` | `tests/Unit/OrderRulesTest.php`, `tests/E2E/BriefRequirementsE2ETest.php` | `docs/testing/test-scenarios.md` |
| ERR-01 401/403/404 | `app/Core/Auth.php`, `views/errors/403.php`, `404.php`, `500.php` | `tests/E2E/EmptyStatesAndFailuresE2ETest.php`, `tests/E2E/SecurityE2ETest.php` | `docs/testing/known-bugs.md` |
| UI-01 Responsif & usability | `public/assets/css/style.css`, `public/assets/js/confirm-dialog.js` | verifikasi manual (lihat catatan) | `docs/testing/test-scenarios.md` |
| DB-01 Schema & transaksi | `database/schema.sql`, `app/Repository/PdoTransactionManager.php` | `tests/Integration/*` | `docs/planning/erd.md`, `docs/architecture/database-design.md` |
| JOB-01 Script terjadwal | `scripts/check-low-stock.php` | `tests/Unit/LowStockDetectionTest.php`, `tests/E2E/LowStockCliE2ETest.php` | README |
| ARCH-01 Layer & interface | `app/Repository/*RepositoryInterface.php`, `MySql*`, `InMemory*` | `tests/Unit/ArchitectureTest.php`, `tests/Unit/InMemoryRepositoriesTest.php` | ADR-001, `docs/architecture/class-diagram-as-built.md` |
| ARCH-02 Stok aman konkurensi | `app/Repository/MySqlProductStockRepository.php` (`FOR UPDATE`) | `tests/Integration/GoodsIssueIntegrationTest.php::testSecondGoodsIssueIsRejectedOnceStockIsExhausted` | ADR-002 |
| DESIGN-01 Class diagram | - | - | `docs/planning/class-diagram-initial.md`, `docs/architecture/class-diagram-as-built.md` |
| DESIGN-02 ADR | - | - | `docs/architecture/adr-001` .. `adr-005` |
| DESIGN-03 Refactor log, SRP, tech debt | - | - | `docs/quality/refactor-log.md` (9 entri), `audit-srp.md`, `tech-debt.md` |
| DESIGN-04 Critique | - | - | `docs/quality/critique.md` |
| TEST-01/02/03 | `tests/Unit`, `tests/Integration` | 157 unit, 9 integration, 117 E2E | `docs/quality/static-analysis-report.txt`, `docs/testing/test-run-output.txt` |

## Catatan verifikasi

- **UI-01** belum punya test otomatis; diverifikasi manual. Screenshot 360px dan desktop untuk dashboard tiga role, detail SO, dan Reports masih harus diambil sebelum defense (task T016 pada `specs/001-ioms-brief-compliance/tasks.md`).
- **Secara sengaja tidak dihitung** sebagai bukti: test E2E bukan syarat brief (§4.3), tetapi dipakai agar coverage Controller/view terukur (ADR-003).
