# Code Critique — Contoh Kode Bermasalah (Sengaja)

> Catatan: Kode di bawah ini **sengaja dibuat bermasalah** sebagai bahan analisis code review. Ini **bukan** kode yang ada di `app/` proyek IOMS — ini adalah simulasi gaya "God Method" / pelanggaran SOLID yang bisa saja terjadi bila `SalesOrderController::store()` ditulis tanpa memisahkan tanggung jawab ke Service, sebagai kontras terhadap kode asli yang justru sudah mendelegasikan ke `SalesOrderService`. **Tidak ada perbaikan/implementasi yang dilakukan di sini — hanya analisis dan rencana**, sesuai instruksi.

## Cuplikan Kode Bermasalah

```php
<?php
// CONTOH BERMASALAH — bukan kode produksi IOMS
namespace App\Controller;

class SalesOrderController
{
    public function store($request)
    {
        // Hardcoded credential untuk koneksi DB "cadangan" — pelanggaran keamanan.
        $pdo = new \PDO('mysql:host=127.0.0.1;dbname=ioms', 'root', 'Sup3rSecret!2024');

        $data = $request['body'];

        // Validasi tercampur langsung di controller
        $errors = [];
        if (empty($data['so_number'])) { $errors[] = 'SO number required'; }
        if (empty($data['customer_id'])) { $errors[] = 'Customer required'; }
        if (empty($data['items']) || count($data['items']) == 0) { $errors[] = 'Items required'; }
        foreach ($data['items'] as $i => $item) {
            if (empty($item['product_id'])) { $errors[] = "Item $i missing product"; }
            if ($item['qty'] <= 0) { $errors[] = "Item $i qty must be positive"; }
        }
        if (count($errors) > 0) {
            echo json_encode(['errors' => $errors]);
            return;
        }

        // Cek duplikat so_number langsung query SQL
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sales_orders WHERE so_number = '" . $data['so_number'] . "'");
        $stmt->execute();
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['errors' => ['SO number already exists']]);
            return;
        }

        // Insert langsung tanpa transaksi
        $pdo->exec("INSERT INTO sales_orders (so_number, customer_id, warehouse_id, created_by, status, order_date)
                     VALUES ('{$data['so_number']}', {$data['customer_id']}, {$data['warehouse_id']}, {$data['user_id']}, 'Draft', '{$data['order_date']}')");
        $soId = $pdo->lastInsertId();

        $total = 0;
        foreach ($data['items'] as $item) {
            $pdo->exec("INSERT INTO sales_order_items (sales_order_id, product_id, qty, selling_price)
                         VALUES ($soId, {$item['product_id']}, {$item['qty']}, {$item['selling_price']})");
            $total += $item['qty'] * $item['selling_price'];

            // Auto-approve kalau total kecil, logic bisnis nyelip di sini juga
            if ($total < 100000) {
                $pdo->exec("UPDATE sales_orders SET status = 'Approved' WHERE id = $soId");
            }
        }

        // Formatting response manual, HTML dicampur dengan JSON API
        header('Content-Type: application/json');
        echo '<div class="alert">Order created!</div>';
        echo json_encode(['id' => $soId, 'total' => $total]);
    }
}
```

## Analisis Pelanggaran

### Pelanggaran SOLID

- **SRP (Single Responsibility)** — method `store()` melakukan: koneksi DB, validasi input, query duplikat, insert order, insert item, kalkulasi total, keputusan auto-approve (logic bisnis), dan formatting response (HTML+JSON campur). Ada lebih dari lima alasan berbeda untuk mengubah method ini.
- **OCP (Open/Closed)** — aturan "auto-approve jika total < 100000" ditulis sebagai `if` yang menyelinap di tengah loop insert item; menambah aturan approval baru berarti mengedit langsung method ini, bukan meng-extend lewat abstraksi (bandingkan dengan `SalesOrderService::TRANSITIONS` + `assertTransition()` di kode asli yang terpusat dan mudah di-extend).
- **DIP (Dependency Inversion)** — controller membuat `new \PDO(...)` secara langsung, bukan bergantung pada `SalesOrderRepositoryInterface` (bandingkan dengan pola asli di ADR-001). Ini juga menghilangkan kemampuan untuk unit test tanpa DB sungguhan.
- **LSP tidak relevan langsung di sini** karena tidak ada hierarki subclass yang diuji, tapi tidak adanya interface berarti tidak ada substitutability sama sekali untuk diuji.

### Code Smell Lain

- **Hardcoded credential** (`root` / `Sup3rSecret!2024`) langsung di source code — risiko keamanan serius jika ter-commit ke VCS.
- **SQL Injection** — semua query dibangun dengan string interpolation langsung dari input user (`$data['so_number']`, `$data['customer_id']`, dst) tanpa prepared statement parameter binding, rentan terhadap SQL injection.
- **Tidak ada transaksi (`beginTransaction`/`commit`)** — insert order dan insert item dilakukan sebagai statement terpisah; jika insert item kedua gagal, order sudah terlanjur tersimpan tanpa item lengkap (data tidak konsisten), berbeda dari pola transaksional yang dipakai `PurchaseOrderService::receiveGoods` dan `SalesOrderService::fulfill` di kode asli.
- **Business logic tersembunyi ("shotgun surgery" risk)** — aturan auto-approve ada di tengah loop insert item, bukan di satu tempat terpusat seperti `TRANSITIONS`/`assertTransition` pada kode asli — perubahan aturan approval berisiko lolos ditemukan saat maintenance.
- **Response format tidak konsisten** — mencampur `echo` HTML (`<div class="alert">`) dengan `json_encode`, membuat response tidak valid sebagai salah satu format (bandingkan dengan `Response::json()` di kode asli yang konsisten).
- **Tidak ada validasi tipe/exception yang jelas** — error dikumpulkan sebagai array string biasa lalu di-echo langsung, tidak ada kelas Exception domain seperti `ValidationException` pada kode asli sehingga tidak bisa dibedakan jenis kegagalannya oleh pemanggil.

## Rencana Perbaikan (Analisis Saja, TIDAK Diimplementasikan)

1. Pindahkan seluruh logic (validasi, cek duplikat, insert, kalkulasi, keputusan approval) ke `SalesOrderService::create()` yang menerima Repository lewat constructor injection — controller hanya memanggil Service dan menerjemahkan hasil/exception ke Response HTTP, mengikuti pola yang sudah dipakai `SalesOrderController::store()` yang asli.
2. Ganti seluruh string-interpolated SQL dengan prepared statement (`$pdo->prepare(...)` + `execute([...])`), seperti pada `MySqlSalesOrderRepository` asli.
3. Hapus hardcoded credential — pindahkan ke environment variable via `App\Core\Env`, sesuai pola `Database::connection()` yang sudah ada.
4. Bungkus insert order + insert items dalam satu transaksi PDO dengan rollback saat gagal, seperti pola di `SalesOrderService::fulfill()`.
5. Ekstrak aturan "auto-approve" (jika memang dibutuhkan sebagai fitur) menjadi bagian dari state machine terpusat (`TRANSITIONS`) atau kelas `SalesOrderApprovalPolicy` terpisah (lihat `docs/quality/audit-srp.md`), bukan inline di tengah loop.
6. Standarkan response memakai `App\Core\Response::json()` saja untuk endpoint API/AJAX, tanpa mencampur markup HTML.
