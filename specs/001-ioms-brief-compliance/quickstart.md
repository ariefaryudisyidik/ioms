# Quickstart: Verifikasi Kepatuhan IOMS

Urutan verifikasi sebelum technical defense. Semua perintah dari root repo.

```bash
# 1. Docker dari kondisi bersih (brief §5.1)
docker compose down && docker volume rm ioms_db_data   # atau: composer db:reset
docker compose up -d --build
# login: admin@ioms.test, sari.sales@ioms.test, rudi.warehouse@ioms.test (Password123!)

# 2. Unit + Integration + E2E dengan coverage gabungan
composer coverage            # hasil: build/coverage/clover.xml, junit.xml

# 3. Static analysis
vendor/bin/phpstan analyse --no-progress
vendor/bin/phpcs

# 4. SonarQube (butuh token dari My Account > Security)
export SONAR_TOKEN=<token>
composer sonar               # buka http://localhost:9000/dashboard?id=ioms

# 5. Script terjadwal (JOB-01)
docker compose exec app php scripts/check-low-stock.php
```

Kriteria lulus: semua test hijau, coverage 100%, PHPStan 0 error, PHPCS 0 error, quality gate Sonar
Passed dengan 0 isu, ketiga dashboard dan CSV terbuka, goods issue kedua ditolak saat stok habis.
