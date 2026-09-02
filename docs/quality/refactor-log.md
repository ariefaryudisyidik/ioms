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

**Tindak lanjut (dieksekusi, commit `refactor:`):** pola "pastikan baris ada sebelum dikunci" di `lockForUpdate()` diekstrak menjadi private helper `ensureRowExists(PDO $pdo, int $productId, int $warehouseId): void` di `MySqlProductStockRepository`. `lockForUpdate()` kini hanya memanggil helper ini lalu melakukan `SELECT ... FOR UPDATE`, memisahkan "memastikan baris ada" dari "mengunci baris" sebagai dua langkah yang masing-masing bisa dibaca sendiri. Divalidasi dengan menjalankan ulang seluruh test suite (unit + integration terhadap MySQL nyata di Docker) — tetap hijau tanpa perubahan perilaku.
