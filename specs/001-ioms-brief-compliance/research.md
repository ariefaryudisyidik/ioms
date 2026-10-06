# Research: Kepatuhan IOMS terhadap Project Brief

Semua NEEDS CLARIFICATION pada Technical Context sudah terjawab dari repo dan brief; tidak ada yang tersisa.

## R1. Cara menetapkan baseline kepatuhan
- **Decision**: tabel keterlacakan requirement -> kode/test/dokumen di `spec.md`, status Done / Perlu sinkron / Verifikasi.
- **Rationale**: brief menilai bukti yang dapat ditelusuri (§3, §8.1), bukan klaim.
- **Alternatives considered**: audit kode penuh baru (lebih mahal, kode sudah 283 test hijau); checklist generik (kurang spesifik per ID brief).

## R2. Menjalankan Rudis tanpa membuat branch
- **Decision**: `create-new-feature.sh` memakai default tanpa branch, dan `RUDIS_FEATURE=001-ioms-brief-compliance` dipakai untuk `setup-plan.sh`.
- **Rationale**: command `/rudis.specify` mewajibkan nol perubahan git secara default; proyek bekerja langsung di `master`.
- **Alternatives considered**: `--create-branch` (mengubah alur git yang tidak diminta).

## R3. Sumber angka test dan coverage
- **Decision**: satu sumber, yaitu output `composer coverage` terbaru (`build/coverage/junit.xml`, `clover.xml`); dokumen mengutip run itu.
- **Rationale**: dokumen saat ini menyebut 278 test, padahal run terbaru 283 (coverage 100%, 3558 baris).
- **Alternatives considered**: menulis angka manual per dokumen (sumber basi berulang).

## R4. Keamanan konfigurasi alat bantu
- **Decision (final)**: pemilik menyatakan pengosongan disengaja agar Claude dapat membaca `.env`; daftar tidak dipulihkan dan keputusan dicatat sebagai D7. Draf awal menyarankan pemulihan (rm -rf, force push, baca/tulis `.env`, kunci `.pem`/`~/.ssh`/`~/.aws`).
- **Rationale**: constitution IV dan brief §4.2 melarang kebocoran secret; perubahan terdeteksi tanpa catatan alasan.
- **Alternatives considered**: membiarkan kosong (menurunkan guard terhadap pembacaan `.env`).

## R5. Verifikasi SonarQube
- **Decision**: `composer sonar` (coverage lalu scan) dengan `SONAR_TOKEN` dari pemilik; target gate Passed dan 0 isu.
- **Rationale**: scanner hanya membaca `clover.xml`; coverage harus dibuat sebelum scan.
- **Alternatives considered**: scan tanpa coverage (coverage kode baru terbaca 0% dan gate gagal).

## R6. Persyaratan ambigu: Warehouse Staff "Boleh mengusulkan" PO
- **Decision**: dicatat sebagai keputusan di `docs/planning/` (implementasi saat ini: Warehouse Staff dapat membuat PO; Admin yang mengelola).
- **Rationale**: FAQ #12 brief meminta jawaban trainer dicatat sebagai catatan keputusan.
- **Alternatives considered**: mengubah scope diam-diam.
