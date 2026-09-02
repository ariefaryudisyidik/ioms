# Class Diagram — Draft Awal (Sebelum Coding)

Diagram ini adalah rencana desain awal sebelum implementasi dimulai. Versi ini disederhanakan dibanding hasil akhir (lihat `docs/architecture/class-diagram-as-built.md` untuk versi nyata) — belum memasukkan seluruh Exception domain maupun implementasi In-Memory untuk testing.

```mermaid
classDiagram
    class Router {
        +get(path, handler)
        +post(path, handler)
        +dispatch(request)
    }
    class Auth {
        +login(user)
        +check()
        +requireRole(...roles)
    }
    class Database {
        +connection() PDO
    }

    class Product {
        +id
        +sku
        +name
        +reorderPoint
    }
    class PurchaseOrder {
        +id
        +status
        +items
    }
    class SalesOrder {
        +id
        +status
        +items
    }

    class ProductRepositoryInterface {
        <<interface>>
        +findById(id)
        +save(product)
    }
    class PurchaseOrderRepositoryInterface {
        <<interface>>
        +findById(id)
        +save(po)
    }
    class SalesOrderRepositoryInterface {
        <<interface>>
        +findById(id)
        +save(so)
    }
    class ProductStockRepositoryInterface {
        <<interface>>
        +lockForUpdate(productId, warehouseId)
        +increment(...)
        +decrement(...)
    }

    class ProductService {
        +create(data)
        +update(id, data)
    }
    class PurchaseOrderService {
        +create(data)
        +transitionTo(id, status)
        +receiveGoods(id, items)
    }
    class SalesOrderService {
        +create(data)
        +submitForApproval(id)
        +approve(id)
        +fulfill(id)
    }

    class ProductController {
        +index()
        +store()
    }
    class PurchaseOrderController {
        +store()
        +receive()
    }
    class SalesOrderController {
        +store()
        +approve()
        +fulfill()
    }

    ProductController --> ProductService
    PurchaseOrderController --> PurchaseOrderService
    SalesOrderController --> SalesOrderService

    ProductService --> ProductRepositoryInterface
    PurchaseOrderService --> PurchaseOrderRepositoryInterface
    PurchaseOrderService --> ProductStockRepositoryInterface
    SalesOrderService --> SalesOrderRepositoryInterface
    SalesOrderService --> ProductStockRepositoryInterface

    PurchaseOrderRepositoryInterface --> PurchaseOrder
    SalesOrderRepositoryInterface --> SalesOrder
    ProductRepositoryInterface --> Product

    Router --> Auth
    Auth --> Database
```

## Rencana awal (ringkas)

- Layering: Controller → Service → Repository Interface → implementasi konkret (rencana awal hanya berasumsi 1 implementasi MySQL, belum direncanakan implementasi In-Memory untuk unit test).
- Setiap entitas transaksi (PO/SO) direncanakan punya Service sendiri yang menangani validasi + transisi status.
- Belum ada rencana eksplisit untuk kelas Exception domain terpisah (awalnya diasumsikan validasi cukup dengan array error biasa).
- Stok direncanakan memakai locking, namun mekanisme detail (SELECT FOR UPDATE vs alternatif lain) baru diputuskan saat desain arsitektur — lihat ADR-002.
