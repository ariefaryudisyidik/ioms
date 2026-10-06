# Checklist Sebelum Submission (brief §10)

Diperiksa 2026-10-06. "Terpenuhi" berarti ada bukti yang bisa ditelusuri; keterbatasan ditulis apa adanya.

| # | Item brief §10 | Status | Bukti / catatan |
|---|---|---|---|
| 1 | Seluruh requirement wajib (§2) diuji pada release/tag final | Terpenuhi | 283 test lulus (157 unit, 9 integration, 117 E2E), `docs/testing/test-run-output.txt`; peta di `docs/testing/requirement-traceability.md`; tag final `v1.0.1` dibuat pada commit akhir setelah scan Sonar ulang |
| 2 | Aplikasi dan database berjalan lewat Docker dari folder bersih | Terpenuhi | `docs/testing/docker-clean-run.md`: clone bersih, `docker compose up --build`, login tiga role |
| 3 | Unit dan integration test berjalan dengan satu perintah dan lulus | Terpenuhi | `composer coverage` (MySQL sementara di Docker, 283 lulus). `composer test:unit` untuk unit saja. Catatan: `composer test` tanpa MySQL menjalankan integration yang butuh database |
| 4 | Laporan static analysis nol critical error | Terpenuhi | `docs/quality/static-analysis-report.txt`: PHPStan 0 error, PHPCS 0 error, 139 warning "line length > 120" dijelaskan |
| 5 | Class diagram initial dan as-built sesuai kode aktual | Terpenuhi | `docs/planning/class-diagram-initial.md`, `docs/architecture/class-diagram-as-built.md`. Sampel 5 kelas ditelusuri ke kode: `SalesOrderService` (final class, bergantung pada `TransactionManagerInterface`), `MySqlReportRepository` (extends `AbstractMySqlRepository`, implements `ReportRepositoryInterface`), `PdoTransactionManager`, `CrudController` (abstract), `InMemoryProductStockRepository` (implements `ProductStockRepositoryInterface`); deklarasi dan relasi cocok dengan diagram |
| 6 | Goods issue/receipt transaksional; skenario oversell tidak dapat direproduksi | Terpenuhi | `FOR UPDATE` + satu transaksi (ADR-002, ADR-005); `GoodsIssueIntegrationTest` dan `TransactionRollbackIntegrationTest` lulus (TS-47) |
| 7 | Segregation of duties teruji di server, bukan hanya UI | Terpenuhi | Unit: `testSalesRoleCannotApproveEvenWhenCallingServiceDirectly`, `testCreatorCannotApproveOwnOrder`, `testWarehouseStaffRoleCannotApprove`; E2E `SecurityE2ETest` (TS-42) |
| 8 | Search, filter, sort, pagination, dashboard tiga role, dan endpoint JSON dapat didemokan | Terpenuhi | TS-36, TS-38, TS-29 sampai TS-31; screenshot `docs/testing/screenshots/`; API 200/404/401 terverifikasi di clone bersih |
| 9 | Tidak ada secret, credential aktif, data client, atau PII di repo dan history | Terpenuhi dengan catatan | `.env` tidak pernah di-commit (0 kejadian di history), tidak ada berkas `.pem`/`.key`, pemindaian pola secret pada berkas ter-track tidak menemukan kredensial, token SonarQube yang pernah ditempel di chat tidak ada di working tree maupun history. Seed memakai nama fiktif dan satu hash bcrypt demo. Catatan: pemindaian pola tidak menjamin menyeluruh; token Sonar sebaiknya dicabut (revoke) |
| 10 | README diuji dari environment bersih; akun demo tiga role tersedia | Terpenuhi | `docs/testing/docker-clean-run.md`; akun demo di README ("Akun Demo") |
| 11 | Peserta dapat menjelaskan data flow, layered architecture, satu ADR, dan satu refactor tanpa bantuan AI | Tanggung jawab peserta | Tidak dapat diverifikasi dari repo. Bahan latihan: penjelasan oversell di `docs/testing/test-scenarios.md`, ADR-002, `docs/quality/refactor-log.md` (entri 7 dan 8), `ai-usage-log.md` |
| 12 | Release/tag final dibuat dan link submission benar | Tag dibuat, link oleh pemilik | Tag lokal `v1.0.1`; push tag dan link submission dikerjakan pemilik |

## Item yang masih terbuka

- **Jawaban trainer atas D1** (Warehouse Staff "Boleh mengusulkan" PO): lihat `docs/planning/decisions.md`; tanyakan sebelum defense.
- **Seed tepat 25 order** (13 PO + 12 SO), sama dengan batas minimum brief tanpa margin.
