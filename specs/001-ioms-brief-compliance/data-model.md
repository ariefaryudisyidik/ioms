# Data Model: Kepatuhan IOMS terhadap Project Brief

Fitur ini tidak menambah entitas atau tabel. Model data mengikuti brief §1.3 dan sudah ada di
`database/schema.sql` serta `docs/planning/erd.md`. Satu perubahan terkini: urutan kolom
`sales_orders` disamakan dengan `purchase_orders` (tanpa perubahan struktur atau constraint).

| Entitas | Tabel | Kunci / constraint penting |
| ------- | ----- | -------------------------- |
| User | `users` | email UNIQUE, role enum Admin/Sales/WarehouseStaff, `is_active` |
| Warehouse | `warehouses` | `is_active` |
| Category | `categories` | nama |
| Product | `products` | sku UNIQUE, harga beli/jual, `reorder_point`, `is_active` |
| ProductStock | `product_stocks` | UNIQUE (product_id, warehouse_id), CHECK quantity >= 0 |
| Supplier / Customer | `suppliers`, `customers` | `is_active` |
| PurchaseOrder + Item | `purchase_orders`, `purchase_order_items` | po_number UNIQUE; status Draft/Ordered/PartiallyReceived/Received/Cancelled; qty_received <= qty_ordered |
| SalesOrder + Item | `sales_orders`, `sales_order_items` | so_number UNIQUE; status Draft/PendingApproval/Approved/Fulfilled/Cancelled; `approved_by` nullable |
| StockLedger | `stock_ledger` | movement_type Receipt/Issue/Adjustment; reference_type/reference_id polimorfik |

## State transitions

- **SalesOrder**: Draft -> PendingApproval -> Approved -> Fulfilled; Cancelled sebelum Fulfilled; Reject dari PendingApproval kembali ke Draft.
- **PurchaseOrder**: Draft -> Ordered -> PartiallyReceived/Received; Cancelled kecuali Received.

## Invarian (constitution II)

- Perubahan `product_stocks` hanya lewat service yang menulis `stock_ledger` dalam satu transaksi.
- Produk, supplier, customer dinonaktifkan, tidak dihapus.
