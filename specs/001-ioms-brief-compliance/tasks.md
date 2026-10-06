---

description: "Task list: kepatuhan IOMS terhadap Project Brief"
---

# Tasks: Kepatuhan IOMS terhadap Project Brief

**Input**: Design documents from `/specs/001-ioms-brief-compliance/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/api-availability.md, quickstart.md

**Tests**: Tidak ada kode fitur baru, jadi tidak ada task test baru. Task verifikasi menjalankan suite yang ada. Bila verifikasi menemukan defect, test regresi ditambahkan pada task terkait.

**Organization**: Dikelompokkan per user story. Setiap fase adalah satu Bolt dengan checkpoint; commit setelah checkpoint.

## Format: `[ID] [P?] [Story] [FR-###?] Description`

- **[P]**: dapat paralel (berkas berbeda, tanpa dependensi)
- **[Story]**: US1..US4 dari spec.md
- **[FR-###]**: requirement yang dipenuhi task

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Artefak Rudis dan baseline

- [X] T001 Isi constitution dari brief di `.rudis/memory/constitution.md` (v1.0.0, 5 prinsip + 2 bagian + governance)
- [X] T002 Buat `specs/001-ioms-brief-compliance/` (spec, plan, research, data-model, contracts, quickstart)
- [X] T003 [P] [US4] [FR-026] Daftar `permissions.deny` di `.claude/settings.json` sengaja dikosongkan pemilik agar Claude dapat membaca `.env`; tidak dipulihkan, keputusan dicatat sebagai D7 di `docs/planning/decisions.md`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Sumber angka tunggal yang dipakai semua dokumen

**CRITICAL**: Selesaikan sebelum menyentuh dokumen yang memuat angka

- [X] T004 [FR-024] [FR-028] [NFR-001] Jalankan `composer coverage` (2026-10-06): 283 test, 1329 assertion, coverage 100% (3558/3558 baris); catat sebagai sumber angka
- [X] T005 [P] [FR-024] [NFR-002] Jalankan `vendor/bin/phpstan analyse` dan `vendor/bin/phpcs`, perbarui `docs/quality/static-analysis-report.txt` (PHPStan 0 error, PHPCS 0 error, sisa warning panjang baris dijelaskan)

**Checkpoint**: angka sumber tersedia

---

## Phase 3: User Story 1 - Assessor menelusuri requirement ke bukti (Priority: P1) MVP

**Goal**: Setiap ID requirement brief punya baris bukti yang dapat ditelusuri.

**Independent Test**: Pilih 5 ID acak; setiap bukti menunjuk berkas dan test yang ada.

- [X] T006 [US1] [FR-001] [FR-002] [FR-003] [FR-004] [FR-005] [FR-006] [FR-007] [FR-008] [FR-009] [FR-012] [FR-013] [FR-014] [FR-017] [FR-018] [FR-019] [FR-021] [FR-023] Buat `docs/testing/requirement-traceability.md` dari tabel keterlacakan di spec.md; untuk tiap baris Done, buka berkas/test yang disebut dan pastikan ada (perbaiki rujukan yang salah)
- [X] T007 [P] [US1] [FR-029] Catat keputusan atas requirement ambigu di `docs/planning/decisions.md` (Warehouse Staff "Boleh mengusulkan" PO; PO tidak dibatasi stok; peran laporan CSV), mengacu FAQ #12 brief

**Checkpoint**: tabel keterlacakan lengkap; commit `docs:`

---

## Phase 4: User Story 2 - Dokumen sesuai kode aktual (Priority: P1)

**Goal**: Tidak ada diagram atau angka yang tertinggal dari kode.

**Independent Test**: Telusuri 3 kelas dari class diagram as-built ke kode; bandingkan angka dokumen dengan output T004.

- [X] T008 [US2] [FR-020] Perbarui `docs/architecture/class-diagram-as-built.md`: tambah `ReportRepositoryInterface` dan `MySqlReportRepository`, `ReportService` yang kini bergantung pada interface itu, `totalRetailValue()` pada stock repository, dan helper global `rupiah()`/`roleLabel()`; tandai dependency ke interface vs kelas konkret
- [X] T009 [P] [US2] [FR-011] Perbarui `docs/brd/modules/report.md`: kolom CSV baru (nama dan nomor order, Total Value, Date & Time di akhir), test `ReportServiceTest`, hapus catatan "tidak ada test ReportService"
- [X] T010 [P] [US2] [FR-010] Perbarui `docs/brd/modules/dashboard.md`: kartu Potential Revenue/Margin, PO Awaiting Receipt, Recent Sales Orders (Sales), Low Stock tidak untuk Sales
- [X] T011 [P] [US2] [FR-016] Pastikan `docs/architecture/database-design.md` dan `docs/planning/erd.md` mencatat urutan kolom `sales_orders` yang baru (erd.md sudah diperbarui; database-design.md tidak mendaftar urutan kolom sehingga tidak perlu diubah)
- [X] T012 [US2] [FR-022] Tambah entri di `docs/quality/refactor-log.md` (rename `role_label` -> `roleLabel` untuk php:S100, hapus fitur `notice` yang mati, ekstraksi `fieldError()` di `validation.js`) dan perbarui `docs/quality/audit-srp.md` bila ada kelas yang berubah tanggung jawab
- [X] T013 [US2] [FR-024] [FR-028] Sinkronkan angka (283 test, 1329 assertion, coverage 100%) di `README.md`, `docs/testing/known-bugs.md`, `docs/testing/test-run-output.txt`, dan `docs/quality/sonarqube-report.md`; bagian Docker/DB di README menyebut `composer db:reset` dan `composer sonar`
- [X] T014 [P] [US2] [FR-025] Perbarui `ai-usage-log.md`: sesi ini (laporan CSV, dashboard, format Rupiah, dialog konfirmasi, SonarQube, reset DB, Rudis), output yang dipakai/ditolak, dan bukti verifikasi (test, coverage, phpstan)
- [X] T015 [P] [US2] [FR-011] Perbarui US-22 di `docs/planning/user-story.md` agar sesuai kolom CSV baru dan batasan peran per laporan

**Checkpoint**: sampel 3 kelas dari diagram terlacak ke kode; angka dokumen sama dengan T004; commit `docs:`

---

## Phase 5: User Story 3 - Kesiapan demo dan technical defense (Priority: P2)

**Goal**: Demo 12-15 menit dan defense berjalan tanpa kejutan.

**Independent Test**: Jalankan `quickstart.md` dari folder bersih.

- [X] T016 [US3] [FR-015] Screenshot 360px dan desktop (20 berkas) untuk login, dashboard tiga role, daftar produk/SO, detail SO, dialog konfirmasi, form PO, dan Reports di `docs/testing/screenshots/`, dirujuk dari `test-scenarios.md` (TS-48); tabel dashboard diperbaiki agar muat di 360px
- [X] T017 [US3] [FR-027] [NFR-005] Uji Docker dari folder bersih (2026-10-06): clone, `docker compose up --build`, login tiga role, API, CSV, unit test 157 lulus; hasil di `docs/testing/docker-clean-run.md`
- [X] T018 [US3] [NFR-003] Scan SonarQube dengan token pemilik (2026-10-06): quality gate Passed, 0 isu, 0 hotspot, coverage 100%, duplikasi 0%; `docs/quality/sonarqube-report.md` diperbarui
- [X] T019 [P] [US3] [FR-007] [FR-019] Skenario oversell untuk defense: `GoodsIssueIntegrationTest` dan `TransactionRollbackIntegrationTest` lulus (2 test, 10 assertion) terhadap MySQL nyata; penjelasan ADR-002 dicatat di `docs/testing/test-scenarios.md` (TS-47)
**Checkpoint**: demo dan defense dapat diulang dari awal; commit `docs:`/`test:`

---

## Phase 6: Polish & Cross-Cutting Concerns

- [X] T021 Jalankan `/rudis.analyze` (2026-10-06): 0 CRITICAL, FR 29/29 tercakup; temuan I1, I2, I3, E1, E3 diperbaiki, E2 ditangani di T020, A1 dicatat di `docs/planning/decisions.md` (D1)
- [X] T022 [P] [NFR-004] [SC-003] [SC-005] Ulangi checklist brief §10 (12 item) di `docs/quality/submission-checklist.md`, termasuk sampel 5 kelas diagram -> kode dan pemeriksaan secret di repo dan history; tandai terpenuhi atau catat keterbatasannya
- [X] T020 [US3] [FR-027] `composer sonar` diulang pada commit `45b5f68` (gate Passed, 0 isu, coverage 100%); tag final cukup satu, `v1.0.0`, dipasang pemilik pada commit akhir; push dan link submission ditangani pemilik

---

## Dependencies & Execution Order

- Phase 1 -> Phase 2 -> Phase 3 dan Phase 4 (dapat paralel setelah Phase 2) -> Phase 5 -> Phase 6.
- T013 bergantung pada T004 dan T005; T018 bergantung pada T004 dan token; T020 bergantung pada semua task lain dan dikerjakan paling akhir (Phase 6).
- US1 dan US2 independen; US3 membutuhkan dokumen US2 selesai agar demo konsisten.

## Parallel Example

```text
Setelah Phase 2: T006 dan T007 (US1) bersamaan dengan T008..T015 (US2); dalam US2: T009, T010, T011, T014, T015 saling [P].
```

## Implementation Strategy

1. MVP: Phase 1-3 (baseline keterlacakan) memberi assessor peta bukti.
2. Lanjut Phase 4 agar dokumen sesuai kode (mencegah critical failure diagram tidak sesuai kode).
3. Phase 5 saat siap defense; T020 (tag) dikerjakan paling akhir setelah scan ulang pada commit akhir.
4. Commit per checkpoint dengan pesan conventional; jangan commit `CLAUDE.md`.

## Requirement Coverage

| FR | Task |
| -- | ---- |
| FR-001..FR-005, FR-008, FR-009, FR-012..FR-014, FR-017, FR-021, FR-023 | T006 |
| FR-006, FR-007, FR-018, FR-019 | T006, T019 |
| FR-010 | T010 |
| FR-011 | T009, T015 |
| FR-015 | T016 |
| FR-016 | T011 |
| FR-020 | T008 |
| FR-022 | T012 |
| FR-024 | T004, T005, T013 |
| FR-025 | T014 |
| FR-026 | T003 |
| FR-027 | T017, T020 |
| FR-028 | T004, T013 |
| FR-029 | T007 |
