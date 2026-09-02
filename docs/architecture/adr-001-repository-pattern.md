# ADR-001: Repository Pattern dengan Interface Ganda (MySQL & In-Memory)

## Status
Diterima (Accepted).

## Context

IOMS membutuhkan akses data untuk 10 entitas domain (User, Product, ProductStock, Category, Supplier, Customer, Warehouse, PurchaseOrder, SalesOrder, StockLedger). Layer Service (`app/Service/*`, mis. `SalesOrderService`, `PurchaseOrderService`) berisi logic bisnis penting (validasi, transisi status, transaksi PDO, locking) yang harus bisa diuji otomatis dengan PHPUnit tanpa bergantung pada instance MySQL yang hidup — terutama karena proyek ini melarang penggunaan tooling CI/CD eksternal dan reviewer mungkin menjalankan test tanpa Docker/MySQL siap.

Jika Service memanggil query SQL langsung (mis. lewat PDO di dalam Service), maka:
- Unit test terpaksa menjadi integration test (butuh DB nyata untuk setiap test kecil).
- Mengganti detail penyimpanan (mis. berpindah driver, menambah cache) akan memaksa perubahan pada logic bisnis.
- Sulit memisahkan tanggung jawab "bagaimana data disimpan" dari "aturan bisnis apa yang berlaku".

## Decision

Menerapkan Repository Pattern dengan interface eksplisit di `app/Repository/`, satu interface per entitas/agregat, contoh: `UserRepositoryInterface`, `ProductRepositoryInterface`, `ProductStockRepositoryInterface`, `PurchaseOrderRepositoryInterface`, `SalesOrderRepositoryInterface`, `StockLedgerRepositoryInterface`, `CategoryRepositoryInterface`, `SupplierRepositoryInterface`, `CustomerRepositoryInterface`, `WarehouseRepositoryInterface`.

Setiap interface memiliki minimal dua implementasi konkret:
- **Implementasi MySQL** (`MySqlUserRepository`, `MySqlProductRepository`, `MySqlProductStockRepository`, `MySqlPurchaseOrderRepository`, `MySqlSalesOrderRepository`, `MySqlStockLedgerRepository`, `MySqlCategoryRepository`, `MySqlSupplierRepository`, `MySqlCustomerRepository`, `MySqlWarehouseRepository`) — memakai PDO langsung, dipakai di runtime aplikasi sungguhan.
- **Implementasi In-Memory** (`InMemoryUserRepository`, `InMemoryProductRepository`, `InMemoryProductStockRepository`, `InMemorySalesOrderRepository`) — menyimpan data di array PHP di memori, dipakai sebagai test double di unit test.

Kelas Service (`app/Service/*`) hanya bergantung pada interface, di-inject lewat constructor (constructor injection manual, tidak memakai DI container framework), sesuai prinsip Dependency Inversion (huruf D pada SOLID).

## Consequences

**Positif:**
- Unit test Service (mis. `SalesOrderService`, `PurchaseOrderService`) bisa dijalankan cepat dan deterministik tanpa MySQL, cukup dengan `InMemory*Repository`.
- Logic bisnis (validasi transisi status, aturan approval, perhitungan stok) terisolasi dari detail SQL — mudah dibaca dan diuji secara terpisah.
- Mengganti/menambah cara penyimpanan (mis. menambah caching layer) tidak menyentuh Service, cukup menambah implementasi baru dari interface yang sama.

**Negatif / trade-off:**
- Ada duplikasi logic ringan antara implementasi MySQL dan In-Memory (mis. aturan "SKU harus unik" dicek ulang di kedua sisi) — risiko implementasi In-Memory "menipu" test karena perilakunya sedikit berbeda dari MySQL nyata (misalnya semantik locking `lockForUpdate` di In-Memory tidak benar-benar menyimulasikan row-lock MySQL).
- Menambah entitas baru berarti menulis interface + minimal satu implementasi konkret, menambah jumlah file dibanding akses data langsung.
- `ApiController` (lihat `docs/architecture/class-diagram-as-built.md`) memakai implementasi `MySql*Repository` secara langsung tanpa lewat Service — konsistensi pola ini tidak 100% dijaga di seluruh codebase (dicatat sebagai tech debt).
