# Catatan Keputusan (requirement ambigu)

Brief FAQ #12: bila requirement ambigu, ajukan pertanyaan sebelum mengubah scope dan simpan jawaban
trainer sebagai catatan keputusan. File ini mencatat keputusan yang diambil di implementasi saat ini
beserta statusnya. Entri dengan status **Perlu konfirmasi trainer** belum dijawab trainer.

| # | Topik | Rujukan brief | Keputusan implementasi | Status |
|---|---|---|---|---|
| D1 | Warehouse Staff "Boleh mengusulkan" PO | §1.2 | Warehouse Staff dapat membuat PO (Draft) dan memproses goods receipt; Admin juga dapat. Tidak ada status "Diusulkan" terpisah. | Perlu konfirmasi trainer |
| D2 | PO tidak dibatasi stok | §1.1 butir 5 | PO adalah pemesanan ke supplier dan menambah stok; qty PO boleh melebihi stok yang ada. Peringatan stok hanya tampil di Sales Order. | Diputuskan pemilik, 2026-10-06 |
| D3 | Laporan CSV per role | §1.2 | Stock Ledger: Admin dan Warehouse Staff. PO: hanya Admin. SO: Admin dan Sales (Sales hanya order miliknya). | Sesuai tabel §1.2 |
| D4 | Harga order | §1.3 | Harga beli/jual item diambil dari harga produk saat order dibuat (snapshot), bukan input manual; nomor PO/SO dibuat otomatis. | Diputuskan pemilik, 2026-10-05 |
| D5 | Dashboard memuat nilai retail dan margin | DASH-01 | Admin melihat Potential Revenue (stok x harga jual) dan Potential Margin selain Inventory Value; angka teoretis, tanpa diskon. | Diputuskan pemilik, 2026-10-06 |
| D6 | Konfirmasi aksi destruktif | UI-01, §4 | Dialog in-page memakai `<dialog>` native dan Vanilla JS; tidak memakai library. | Sesuai batasan frontend §4 |
| D7 | Daftar `permissions.deny` Claude Code dikosongkan | Constitution IV, brief §4.2 | Dikosongkan sengaja oleh pemilik agar Claude dapat membaca `.env` saat bekerja. Berlaku hanya untuk alat bantu pengembangan lokal (`.claude/settings.json`), bukan aplikasi. Batasan yang tetap berlaku: `.env` tidak ter-track git, hanya `.env.example` yang di-commit, dan secret tidak boleh masuk repo, dokumen, atau commit. | Diputuskan pemilik, 2026-10-06 |
