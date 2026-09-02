# Audit Single Responsibility Principle (SRP)

## Kandidat: `SalesOrderService` (kelas asli, paling "gemuk" saat ini)

`app/Service/SalesOrderService.php` adalah kelas Service paling besar dalam codebase (255 baris, 6 method publik: `create`, `submitForApproval`, `approve`, `reject`, `cancel`, `fulfill`, plus 1 method privat `assertTransition`, dan sebuah tabel state machine `TRANSITIONS`). Kelas ini berpotensi melanggar SRP karena dalam satu class bercampur beberapa alasan-untuk-berubah (reasons to change) yang sebenarnya berbeda:

1. **Validasi input pembuatan SO** (di `create()`) — aturan seperti "so_number wajib & unik", "items minimal 1", "tiap item butuh product_id & qty positif". Alasan berubah: aturan validasi form berubah.
2. **State machine / aturan transisi status** (`TRANSITIONS`, `assertTransition()`) — mendefinisikan alur Draft→PendingApproval→Approved→Fulfilled/Cancelled. Alasan berubah: proses bisnis approval berubah (mis. menambah level approval).
3. **Aturan otorisasi/segregation of duty** (di `submitForApproval`, `approve`, `reject`) — "hanya pemilik order boleh submit", "hanya Admin boleh approve", "approver tidak boleh sama dengan creator". Alasan berubah: kebijakan siapa-boleh-apa berubah, independen dari state machine itu sendiri.
4. **Orkestrasi transaksi & concurrency-safety untuk goods issue** (`fulfill()`) — locking stok, iterasi item, penulisan `StockLedger`, commit/rollback PDO. Alasan berubah: strategi locking/algoritma stok berubah (mis. beralih ke reservasi stok terpisah dari fulfillment).

Saat ini keempat tanggung jawab tersebut hidup berdampingan dalam satu class dengan satu constructor yang men-inject tiga Repository + PDO sekaligus (`SalesOrderRepositoryInterface`, `ProductStockRepositoryInterface`, `StockLedgerRepositoryInterface`, `PDO`), sehingga class ini punya lebih dari satu "customer" konseptual: layer Controller yang butuh validasi CRUD sederhana, dan layer domain yang butuh state machine + logic gudang.

## Dampak jika dibiarkan tumbuh

Untuk skala saat ini (6 method, ~255 baris) kelas ini masih dapat dibaca dan diuji dalam satu file, sehingga belum "harus" dipecah segera. Namun risiko yang mulai terlihat:
- Menambah aturan approval baru (mis. multi-level approval, approval berbasis nilai order) akan menambah panjang `assertTransition`/`approve` tanpa tempat alami untuk aturan otorisasi yang lebih kompleks.
- Menambah strategi fulfillment baru (mis. partial fulfillment seperti pada PO) akan membuat `fulfill()` tumbuh mendekati ukuran `PurchaseOrderService::receiveGoods()`, membuat class ini semakin menjadi tempat "segala hal tentang SO".
- Unit test untuk validasi `create()` harus tetap men-setup dependency stok+ledger+PDO yang sebenarnya tidak relevan untuk skenario itu (test menjadi lebih berat dari yang seharusnya).

## Rencana Pemecahan (jika/ketika kompleksitas bertambah)

Memisahkan menjadi beberapa kelas kolaborator, masing-masing dengan satu alasan untuk berubah:

1. **`SalesOrderValidator`** — memvalidasi payload `create()` (so_number unik, item minimal 1, dsb), dipanggil oleh `SalesOrderService::create()`. Alasan berubah: aturan format/kelengkapan data.
2. **`SalesOrderStatusMachine`** (atau `SalesOrderTransitionPolicy`) — memegang konstanta `TRANSITIONS` dan method `assertTransition()`, dipakai bersama oleh seluruh method transisi. Alasan berubah: alur status bisnis.
3. **`SalesOrderApprovalPolicy`** — memutuskan siapa boleh submit/approve/reject (cek role, cek creator ≠ approver), dipanggil sebelum `SalesOrderStatusMachine` dieksekusi. Alasan berubah: kebijakan otorisasi/segregation of duty.
4. **`SalesOrderFulfillmentService`** — khusus menangani `fulfill()`: locking stok via `ProductStockRepositoryInterface::lockForUpdate`, penulisan `StockLedger`, dan transaksi PDO. Alasan berubah: algoritma/strategi pengurangan stok.
5. **`SalesOrderService`** (tersisa, lebih tipis) — menjadi fasad/orchestrator tipis yang meng-inject keempat kolaborator di atas dan mendelegasikan setiap method publik ke kolaborator yang sesuai, sambil tetap menjadi satu-satunya titik masuk yang dipakai `SalesOrderController`.

Pemecahan ini konsisten dengan pola yang sudah dipakai proyek untuk memisahkan Repository dari Service (ADR-001) — setiap kolaborator baru tetap bisa menerima Repository via constructor injection yang sama.
