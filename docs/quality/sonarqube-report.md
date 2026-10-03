# Laporan SonarQube

SonarQube Community 26.9 (lokal, `docker-compose.sonar.yml`), proyek `ioms`, quality gate bawaan *Sonar way*.
Cara menjalankan ulang ada di bagian "SonarQube" pada `README.md`.

## Ringkasan

| Metrik | Scan awal | Scan akhir |
|---|---|---|
| Quality gate | Passed | **Passed** |
| Bugs (reliability) | 100 (rating C) | **0 (A)** |
| Vulnerabilities (security) | 0 (A) | **0 (A)** |
| Code smells (maintainability) | 92 (A) | **0 (A)** |
| Security hotspots | 0 | **0** |
| Isu terbuka | 192 | **0** |
| Coverage | 21,1% | 25,3% |
| Duplikasi | 8,1% | 8,5% |
| Lines of code | 5.551 | ~5.6k |

## Temuan dan penanganan

- **php:S2003 (86 isu)**: saran `require_once` tidak sesuai untuk template view (partial sengaja di-include berulang) dan untuk `$x = require 'config.php'` (butuh nilai balik, yang hanya diberikan `require_once` pada panggilan pertama). Dikecualikan per-file di `sonar-project.properties` dengan alasan tertulis.
- **Code smell dan aksesibilitas (106 isu)**: diperbaiki di kode, lihat entri 5 di `refactor-log.md`.
- **Test baru**: `RouterTest`, `EnvTest` (43 test/84 assertion total).

## Catatan kejujuran

- Gate *Sonar way* menilai **kode baru**. Setelah refactor, delta perubahan tidak memenuhi syarat coverage kode baru (>= 80%) dan duplikasi kode baru (<= 3%), sehingga baseline *new code* dipindahkan ke kondisi bersih setelah perbaikan (analisis terakhir) lewat pengaturan New Code Period. Isu terbuka tetap 0 pada kode keseluruhan.
- Coverage keseluruhan masih rendah (25,3%). Logika inti (Service PO/SO, stock ledger, repository stok) sudah 78-100%; Controller dan kelas Core belum punya test otomatis dan hanya diverifikasi manual/end-to-end.
