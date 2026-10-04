# Refactor Log

Catatan refactor konkret selama pengembangan/review IOMS. Format: Code smell → Teknik → Sebelum/Sesudah.

## 1. Duplikasi logic validasi transisi status (Extract Method + konsolidasi konstanta)

**Code smell:** `PurchaseOrderService` dan `SalesOrderService` awalnya masing-masing memuat pengecekan transisi status dengan gaya `if/elseif` panjang yang tersebar di setiap method publik (`transitionTo`, `submitForApproval`, `approve`, `reject`, `cancel`), membuat aturan transisi sulit dibaca sebagai satu kesatuan dan rawan tidak konsisten antar method.

**Teknik:** Extract Method — menyatukan seluruh aturan menjadi satu peta konstanta (`TRANSITIONS`) per Service dan satu method privat `assertTransition()`/`assertTransitionAllowed()` yang dipanggil semua method publik terkait status.

**Sebelum (representatif):**
```php
public function approve(int $soId, int $approverId, string $approverRole): SalesOrder
{
    $so = $this->salesOrders->findById($soId);
    if ($so->status !== 'PendingApproval') {
        throw new \RuntimeException('Cannot approve from ' . $so->status);
    }
    // ... duplikasi cek serupa di reject(), cancel(), submitForApproval() ...
}
```

**Sesudah (kode nyata di `app/Service/SalesOrderService.php`):**
```php
private const TRANSITIONS = [
    SalesOrder::STATUS_DRAFT => [SalesOrder::STATUS_PENDING_APPROVAL, SalesOrder::STATUS_CANCELLED],
    SalesOrder::STATUS_PENDING_APPROVAL => [SalesOrder::STATUS_APPROVED, SalesOrder::STATUS_DRAFT, SalesOrder::STATUS_CANCELLED],
    SalesOrder::STATUS_APPROVED => [SalesOrder::STATUS_FULFILLED, SalesOrder::STATUS_CANCELLED],
    SalesOrder::STATUS_FULFILLED => [],
    SalesOrder::STATUS_CANCELLED => [],
];

private function assertTransition(string $from, string $to): void
{
    if ($to === SalesOrder::STATUS_CANCELLED) {
        if (in_array($from, [SalesOrder::STATUS_FULFILLED, SalesOrder::STATUS_CANCELLED], true)) {
            throw InvalidStatusTransitionException::make($from, $to);
        }
        return;
    }
    if (!in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
        throw InvalidStatusTransitionException::make($from, $to);
    }
}
```
Setiap method publik (`approve`, `reject`, `cancel`, `submitForApproval`, `fulfill`) kini cukup memanggil `$this->assertTransition($so->status, TargetStatus)`, sehingga seluruh mesin status ada di satu tempat yang bisa dibaca dan diuji sekali saja.

## 2. "Magic string" error message diganti Exception domain bertipe (Introduce Value Object / Exception)

**Code smell:** Error validasi dan error otorisasi awalnya rawan dilempar sebagai `RuntimeException` generik dengan pesan bebas, menyulitkan `Controller::handle` untuk memutuskan kode HTTP yang tepat (semua error akan terlihat sama sebagai 500).

**Teknik:** Introduce dedicated Exception classes — `ValidationException` (membawa array `errors()` per field), `InvalidStatusTransitionException::make($from, $to)`, `InsufficientStockException::forProduct($id, $requested, $available)`, `AuthorizationException::forbidden($message)` — masing-masing di `app/Service/Exception/`.

**Sebelum (representatif):**
```php
if ($errors) {
    throw new \RuntimeException('Validation failed: ' . implode(', ', $errors));
}
```

**Sesudah (kode nyata):**
```php
if ($errors) {
    throw new ValidationException($errors);
}
// ...
final class ValidationException extends RuntimeException
{
    public function __construct(private array $errors, string $message = 'Validation failed.')
    {
        parent::__construct($message);
    }
    public function errors(): array { return $this->errors; }
}
```
Sekarang Controller bisa `catch (ValidationException $e)` secara spesifik dan mengembalikan 422 dengan `$e->errors()` per field, tanpa parsing string pesan.

## 3. Boy Scout Rule — pembersihan kecil di luar scope saat menelusuri `MySqlProductStockRepository`

**Konteks:** Saat menelusuri `lockForUpdate()` untuk keperluan ADR-002 (di luar task utama menulis dokumentasi), ditemukan bahwa method ini melakukan `INSERT ... ON DUPLICATE KEY UPDATE product_id = product_id` — pola idiom yang benar untuk memastikan baris ada tanpa mengubah data, namun tanpa komentar akan membingungkan pembaca baru yang mengira ini bug (kenapa meng-update kolom dengan nilainya sendiri?).

**Tindakan Boy Scout:** Menambahkan/mempertahankan komentar penjelas satu baris tepat di atas query tersebut (`// Ensure a row exists so it can be locked deterministically.`) — komentar ini sudah ada di kode dan dikonfirmasi tetap dipertahankan sebagai dokumentasi inline yang benar, bukan dihapus saat refactor lain menyentuh file ini. Prinsipnya: setiap kali file ini disentuh untuk keperluan apa pun, baris komentar krusial seperti ini dijaga agar tidak hilang, karena tanpanya intent locking-nya tidak terlihat jelas hanya dari baca SQL mentah.

**Tindak lanjut (dieksekusi, commit `refactor:`):** pola "pastikan baris ada sebelum dikunci" di `lockForUpdate()` diekstrak menjadi private helper `ensureRowExists(int $productId, int $warehouseId): void` (parameter `PDO` yang semula ada dihapus pada entri 7) di `MySqlProductStockRepository`. `lockForUpdate()` kini hanya memanggil helper ini lalu melakukan `SELECT ... FOR UPDATE`, memisahkan "memastikan baris ada" dari "mengunci baris" sebagai dua langkah yang masing-masing bisa dibaca sendiri. Divalidasi dengan menjalankan ulang seluruh test suite (unit + integration terhadap MySQL nyata di Docker) — tetap hijau tanpa perubahan perilaku.

## 4. Dashboard melakukan 8 query count terpisah untuk satu halaman (Consolidate Query / GROUP BY)

**Code smell:** `DashboardService::summaryFor()` memanggil `SalesOrder::countByStatus()` lima kali (sekali per status) dan `PurchaseOrder::countSearch(['status' => ...])` tiga kali secara berurutan hanya untuk menampilkan rekap status di satu halaman dashboard — total 8 round-trip database untuk data yang sebenarnya bisa didapat dari 2 query. Pola ini sama dengan gejala N+1 yang dibahas di modul training SQL Bab 12: banyak query kecil yang seharusnya bisa digabung jadi satu query agregasi.

**Teknik:** Consolidate Query — mengganti seluruh pemanggilan per-status dengan satu method `countsByStatus(): array<string,int>` per repository yang menjalankan `SELECT status, COUNT(*) AS cnt FROM ... GROUP BY status` sekali, lalu Service membaca hasilnya dari array dengan fallback `?? 0` untuk status yang tidak muncul di data.

**Sebelum (representatif, `app/Service/DashboardService.php`):**
```php
$summary = [
    'pending_approval_count' => $this->salesOrders->countByStatus(SalesOrder::STATUS_PENDING_APPROVAL),
    'so_draft_count' => $this->salesOrders->countByStatus(SalesOrder::STATUS_DRAFT),
    'so_approved_count' => $this->salesOrders->countByStatus(SalesOrder::STATUS_APPROVED),
    'so_fulfilled_count' => $this->salesOrders->countByStatus(SalesOrder::STATUS_FULFILLED),
    'so_cancelled_count' => $this->salesOrders->countByStatus(SalesOrder::STATUS_CANCELLED),
    'po_draft_count' => $this->purchaseOrders->countSearch(['status' => PurchaseOrder::STATUS_DRAFT]),
    'po_ordered_count' => $this->purchaseOrders->countSearch(['status' => PurchaseOrder::STATUS_ORDERED]),
    'po_received_count' => $this->purchaseOrders->countSearch(['status' => PurchaseOrder::STATUS_RECEIVED]),
];
```

**Sesudah (kode nyata):**
```php
$soCounts = $this->salesOrders->countsByStatus();
$poCounts = $this->purchaseOrders->countsByStatus();

$summary = [
    'pending_approval_count' => $soCounts[SalesOrder::STATUS_PENDING_APPROVAL] ?? 0,
    'so_draft_count' => $soCounts[SalesOrder::STATUS_DRAFT] ?? 0,
    // ...
    'po_ordered_count' => $poCounts[PurchaseOrder::STATUS_ORDERED] ?? 0,
];
```
`countByStatus(string $status): int` yang jadi tidak terpakai dihapus dari interface dan kedua implementasinya (MySQL + in-memory) agar tidak ada kode mati. Divalidasi dengan menjalankan ulang unit + integration test (tetap hijau) dan membandingkan angka dashboard terhadap hasil `GROUP BY` manual langsung di MySQL — cocok persis.

## 5. Temuan SonarQube: complexity, literal duplikat, parameter mati, aksesibilitas

**Code smell:** scan SonarQube pertama menemukan 192 isu terbuka (100 "bug", 92 code smell). Setelah S2003 (`include`/`require` di template, false positive yang dikecualikan dengan alasan tertulis di `sonar-project.properties`) dipisahkan, sisanya diperbaiki satu per satu.

| Temuan | Teknik | Perubahan |
|---|---|---|
| `Controller::handle()` punya 9 `return` (S1142) | Extract Method | Satu `try/catch (Throwable)` yang mendelegasikan ke `respondValidation/Forbidden/Conflict/Unexpected`; kontrak ERR-01 (422/403/409/500) tidak berubah. |
| `Env::load()` kompleksitas 22 (S3776) | Extract Method | Parsing baris dipisah ke `parseLine()` dan `stripQuotes()`. |
| `PurchaseOrderService::create()` / `SalesOrderService::create()` kompleksitas 16 (S3776) | Extract Method | Validasi item dipindah ke `validateItems()`; `validateOrderDate()` jadi satu titik return. |
| 27 literal URL/pesan duplikat (S1192) | Introduce Constant | `BASE_URL`, `DETAIL_URL_PREFIX`, `LOGIN_URL`, `ORDER_NOT_FOUND`, dst. |
| 43 parameter `$request` tidak terpakai + `$forbiddenUrl` + `$approverId` (S1172) | Remove Parameter | Router sekarang memetakan argumen handler berdasarkan nama parameter (`$request`, `$params`) lewat reflection, sehingga handler hanya mendeklarasikan yang dipakai. `Auth::requireRole()` dan `SalesOrderService::reject()` disederhanakan beserta pemanggilnya. |
| 14 isu aksesibilitas form (label tanpa kontrol, `<th>` tanpa scope, `role="status"`) | Perbaikan markup | Label membungkus kontrolnya, `aria-label` pada input penerimaan barang, `scope="row"`, dan `<output>` untuk flash. |

**Validasi:** unit + integration test hijau (43 test/84 assertion, termasuk `RouterTest` dan `EnvTest` baru untuk perubahan Router dan Env), PHPStan 0 error, PHPCS 0 error, smoke test end-to-end via Docker dari working tree bersih (semua route GET untuk tiga role, alur buat kategori/PO/SO termasuk jalur validasi gagal), dan scan SonarQube ulang menunjukkan 0 isu terbuka.

## 6. Menghilangkan duplikasi (SonarQube CPD 8,1% → 0%)

**Code smell:** scan SonarQube melaporkan ≈670 baris duplikat: lima controller CRUD hampir identik (Supplier, Warehouse, Category, User, Customer), view form/daftar yang disalin-tempel (PO/SO create dan index, product create/edit, customer/supplier create), dan `MySqlSalesOrderRepository`/`MySqlPurchaseOrderRepository`/`MySqlStockLedgerRepository` dengan query builder serupa.

| Duplikasi | Teknik | Perubahan |
|---|---|---|
| Controller CRUD | Template Method / Extract Superclass | `CrudController` memegang alur index/create/store/edit/update/remove; tiap controller hanya mendeklarasikan view, URL dasar, role, dan service. |
| View | Extract Partial | `views/partials/` (`text-field`, `select-field`, `form-footer`, `contact-form`, `product-form`, `order-form`, `order-item-row`, `order-list`) plus helper `partial()`; HTML hasil render dibandingkan sebelum/sesudah (0 selisih selain whitespace). |
| Repository order/ledger | Extract Superclass | `AbstractMySqlRepository` berisi `fetchRows/fetchRow/execute/insert`, `buildWhere` berbasis aturan, `searchRows`, dan `countRows`; SQL dan parameter dibandingkan dengan versi lama (identik). |

**Kode mati yang ikut dibuang:** `Controller::withOldInputOnError`, `View::e`, cabang `default => 'bin'` yang tidak mungkin tercapai di `ProductService`, dan guard `file()` ganda di `Env::load`. Handler error bootstrap diekstrak menjadi `Response::serverError()` agar bisa diuji.

**Validasi:** 166 test/903 assertion hijau (termasuk suite E2E yang membuktikan perilaku HTTP tidak berubah), PHPStan 0 error, PHPCS 0 error, SonarQube 0 isu, 0% duplikasi, coverage 100%.

## 7. Service bergantung pada PDO untuk transaksi (Extract Interface + Remove Parameter)

**Code smell:** `SalesOrderService` dan `PurchaseOrderService` menerima `PDO` lewat constructor hanya untuk `beginTransaction/commit/rollBack`, lalu meneruskannya sebagai parameter ke metode repository (`lockForUpdate($pdo, ...)`, `record($entry, $pdo)`, `updateStatus(..., $pdo)`, `updateItemReceived(..., $pdo)`). Detail teknologi penyimpanan bocor ke lapisan business logic dan membuat unit test memakai `PDO` palsu (sqlite/mock) hanya agar transaksi bisa berjalan.

**Teknik:** Extract Interface (`TransactionManagerInterface::run(callable)` dengan implementasi `PdoTransactionManager` dan `InMemoryTransactionManager`), Remove Parameter (parameter `PDO` dihapus dari interface dan implementasi repository), serta Extract Method (`fulfill()` dipecah menjadi `assertStockAvailable()` dan `issueStock()`; `receiveGoods()` memakai `receiveLine()`).

**Sebelum (representatif):**
```php
$this->pdo->beginTransaction();
try {
    $available = $this->stocks->lockForUpdate($this->pdo, $item->productId, $so->warehouseId);
    // ... decrement, $this->ledger->record($entry, $this->pdo) ...
    $this->pdo->commit();
} catch (Throwable $e) {
    $this->pdo->rollBack();
    throw $e;
}
```

**Sesudah (kode nyata di `app/Service/SalesOrderService.php`):**
```php
return $this->transactions->run(function () use ($so, $userId): SalesOrder {
    $this->assertStockAvailable($so);   // lockForUpdate per item, gagal sebelum menulis apa pun
    $this->issueStock($so, $userId);    // decrement + ledger
    $this->salesOrders->updateStatus((int) $so->id, SalesOrder::STATUS_FULFILLED);
    $so->status = SalesOrder::STATUS_FULFILLED;

    return $so;
});
```

**Validasi:** seluruh test integration terhadap MySQL asli (rollback saat gagal, goods issue kedua ditolak, partial/full receipt) tetap lulus; unit test kini membuktikan commit/rollback lewat `InMemoryTransactionManager` tanpa database; `ArchitectureTest` mencegah `PDO`, session, atau superglobal masuk ke `app/Service`. 252 test, coverage 100%.
