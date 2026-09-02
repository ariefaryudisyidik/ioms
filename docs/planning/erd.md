# Entity Relationship Diagram (ERD)

Dibuat berdasarkan `database/schema.sql` asli (12 tabel/entitas).

```mermaid
erDiagram
    USERS ||--o{ PURCHASE_ORDERS : "created_by"
    USERS ||--o{ SALES_ORDERS : "created_by"
    USERS ||--o{ SALES_ORDERS : "approved_by"
    USERS ||--o{ STOCK_LEDGER : "performed_by"

    WAREHOUSES ||--o{ PRODUCT_STOCKS : has
    WAREHOUSES ||--o{ PURCHASE_ORDERS : "destination"
    WAREHOUSES ||--o{ SALES_ORDERS : "source"
    WAREHOUSES ||--o{ STOCK_LEDGER : "location"

    CATEGORIES ||--o{ PRODUCTS : classifies

    PRODUCTS ||--o{ PRODUCT_STOCKS : "stocked as"
    PRODUCTS ||--o{ PURCHASE_ORDER_ITEMS : "ordered as"
    PRODUCTS ||--o{ SALES_ORDER_ITEMS : "sold as"
    PRODUCTS ||--o{ STOCK_LEDGER : "moved as"

    SUPPLIERS ||--o{ PURCHASE_ORDERS : supplies

    CUSTOMERS ||--o{ SALES_ORDERS : orders

    PURCHASE_ORDERS ||--o{ PURCHASE_ORDER_ITEMS : contains

    SALES_ORDERS ||--o{ SALES_ORDER_ITEMS : contains

    USERS {
        bigint id PK
        varchar name
        varchar email UK
        varchar password_hash
        enum role "Admin|Sales|WarehouseStaff"
        tinyint is_active
        timestamp created_at
        timestamp updated_at
    }

    WAREHOUSES {
        bigint id PK
        varchar name
        varchar location
        tinyint is_active
    }

    CATEGORIES {
        bigint id PK
        varchar name
        text description
    }

    PRODUCTS {
        bigint id PK
        varchar sku UK
        varchar name
        bigint category_id FK
        varchar unit
        decimal purchase_price
        decimal selling_price
        int reorder_point
        varchar image_path
        tinyint is_active
        timestamp created_at
        timestamp updated_at
    }

    PRODUCT_STOCKS {
        bigint id PK
        bigint product_id FK
        bigint warehouse_id FK
        int quantity
        timestamp updated_at
    }

    SUPPLIERS {
        bigint id PK
        varchar name
        varchar contact
        varchar address
        tinyint is_active
    }

    CUSTOMERS {
        bigint id PK
        varchar name
        varchar contact
        varchar address
        tinyint is_active
    }

    PURCHASE_ORDERS {
        bigint id PK
        varchar po_number UK
        bigint supplier_id FK
        bigint warehouse_id FK
        enum status "Draft|Ordered|PartiallyReceived|Received|Cancelled"
        date order_date
        bigint created_by FK
        timestamp created_at
        timestamp updated_at
    }

    PURCHASE_ORDER_ITEMS {
        bigint id PK
        bigint purchase_order_id FK
        bigint product_id FK
        int qty_ordered
        int qty_received
        decimal purchase_price
    }

    SALES_ORDERS {
        bigint id PK
        varchar so_number UK
        bigint customer_id FK
        bigint warehouse_id FK
        bigint created_by FK
        bigint approved_by FK
        enum status "Draft|PendingApproval|Approved|Fulfilled|Cancelled"
        date order_date
        timestamp created_at
        timestamp updated_at
    }

    SALES_ORDER_ITEMS {
        bigint id PK
        bigint sales_order_id FK
        bigint product_id FK
        int qty
        decimal selling_price
    }

    STOCK_LEDGER {
        bigint id PK
        bigint product_id FK
        bigint warehouse_id FK
        enum movement_type "Receipt|Issue|Adjustment"
        int quantity
        varchar reference_type
        bigint reference_id
        bigint performed_by FK
        timestamp created_at
    }
```

## Catatan

- `product_stocks` memiliki `UNIQUE KEY (product_id, warehouse_id)` — satu baris per kombinasi produk-gudang, dengan `CHECK (quantity >= 0)`.
- `stock_ledger.reference_type`/`reference_id` adalah polymorphic reference (bukan FK sungguhan) yang menunjuk ke `purchase_orders` atau `sales_orders` tergantung `movement_type` (`Receipt` → PO, `Issue` → SO).
- `sales_orders.approved_by` nullable (`ON DELETE SET NULL`), hanya terisi setelah status `Approved`.
- Semua kolom status memakai `ENUM` MySQL native sesuai daftar status di masing-masing Service (`PurchaseOrderService::TRANSITIONS`, `SalesOrderService::TRANSITIONS`).
