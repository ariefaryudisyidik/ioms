# Desain Database: Satu Transaksi Multi-Tabel dan Satu Index

Dokumen ini memenuhi bukti DB-01 pada brief. ERD ada di `docs/planning/erd.md`; skema dan seed ada di `database/schema.sql` dan `database/seed.sql`.

## Constraint yang menjaga integritas

| Tabel | Constraint | Tujuan |
|---|---|---|
| `product_stocks` | `UNIQUE (product_id, warehouse_id)`, `CHECK (quantity >= 0)`, FK ke `products` dan `warehouses` | Satu baris stok per produk per gudang; stok tidak bisa negatif walau aplikasi salah |
| `purchase_order_items`, `sales_order_items` | `CHECK (qty_* >= 0)`, FK ke order dan produk | Item tidak bisa menunjuk order/produk yang tidak ada |
| `stock_ledger` | FK ke produk, gudang, dan `users`; `movement_type ENUM('Receipt','Issue','Adjustment')` | Setiap pergerakan stok bisa ditelusuri ke pelaku dan referensinya |
| `products` | `UNIQUE (sku)`, `CHECK (reorder_point >= 0)` | SKU unik; angka valid |
| `users` | `UNIQUE (email)`, `ENUM` role | Email unik; role terbatas pada tiga nilai |
| `login_attempts` | index `(email, ip_address, attempted_at)` dan `(ip_address, attempted_at)` | Throttling login tetap cepat (ADR-004) |

## Satu transaksi multi-tabel: goods issue (`SalesOrderService::fulfill`)

Goods issue mengubah **tiga tabel** dan harus berhasil seluruhnya atau tidak sama sekali:

```text
BEGIN
  untuk setiap item SO:
    SELECT quantity FROM product_stocks WHERE product_id=? AND warehouse_id=? FOR UPDATE   -- kunci baris
    jika quantity < qty item  →  InsufficientStockException  →  ROLLBACK
  untuk setiap item SO:
    UPDATE product_stocks SET quantity = quantity - ?        -- stok berkurang
    INSERT INTO stock_ledger (... movement_type='Issue' ...)  -- riwayat
  UPDATE sales_orders SET status='Fulfilled'                 -- status order
COMMIT
```

- **Atomik:** bila item kedua kekurangan stok, item pertama tidak jadi dikurangi (tidak ada stok setengah berkurang). Dibuktikan oleh `TransactionRollbackIntegrationTest` dan `SalesOrderE2ETest::testFulfillmentWithInsufficientStockRollsBackAndKeepsOrderApproved`.
- **Aman dari race condition:** `FOR UPDATE` membuat dua goods issue untuk produk dan gudang yang sama antre pada row lock; yang kedua membaca stok *setelah* yang pertama commit, sehingga ditolak bila stok habis (ADR-002, `GoodsIssueIntegrationTest::testSecondGoodsIssueIsRejectedOnceStockIsExhausted`).
- **Konsistensi ledger:** `stock_ledger` ditulis di transaksi yang sama dengan perubahan `product_stocks`, jadi jumlah ledger selalu cocok dengan stok. Goods receipt (`PurchaseOrderService::receiveGoods`) memakai pola yang sama untuk `purchase_order_items`, `product_stocks`, `stock_ledger`, dan status PO.

## Satu index: `uq_product_warehouse (product_id, warehouse_id)`

Index unik ini melayani query paling kritis, yaitu pembacaan dan penguncian stok per produk+gudang (`MySqlProductStockRepository::lockForUpdate`, `find`, `increment`, `decrement`), dan sekaligus menegakkan satu baris stok per pasangan.

`EXPLAIN` pada data seed (64 baris stok), dijalankan di MySQL 8:

```sql
-- dengan index
EXPLAIN SELECT quantity FROM product_stocks WHERE product_id = 1 AND warehouse_id = 2 FOR UPDATE;
-- type: const | key: uq_product_warehouse | key_len: 16 | rows: 1

-- index diabaikan (IGNORE INDEX), untuk pembanding
EXPLAIN SELECT quantity FROM product_stocks IGNORE INDEX (uq_product_warehouse, idx_stocks_warehouse_id, idx_stocks_quantity)
        WHERE product_id = 1 AND warehouse_id = 2;
-- type: ALL | key: NULL | rows: 64 | Extra: Using where
```

Dengan index, MySQL langsung menuju satu baris (`const`) dan row lock hanya menyentuh baris itu. Tanpa index terjadi *full table scan* (`ALL`), yang lebih lambat dan pada transaksi dapat mengunci lebih banyak baris sehingga goods issue yang tidak berkaitan saling menunggu. Karena itu index ini penting untuk kebenaran maupun performa.

Index lain yang relevan: `idx_ledger_reference (reference_type, reference_id)` untuk menelusuri baris ledger dari sebuah PO/SO, dan `idx_login_attempts_email_ip` untuk throttling login.
