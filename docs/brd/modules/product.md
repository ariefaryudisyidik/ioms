# Modul: Product (`product`)

**Kembali ke**: [00-overview.md](../00-overview.md) · **Terakhir Diperbarui**: 2026-09-04

## Ringkasan

Master data untuk produk yang dijual/disimpan dan kategorinya, termasuk upload gambar.

## Kapabilitas

- **PRODUCT-CAP-001** — list/cari produk (berdasarkan nama/SKU, kategori, status aktif, sort), dengan paginasi
- **PRODUCT-CAP-002** — lihat detail produk termasuk stok per gudang
- **PRODUCT-CAP-003** — buat produk (SKU, nama, kategori, unit, harga beli/jual, reorder point, gambar opsional)
- **PRODUCT-CAP-004** — edit / nonaktifkan produk
- **PRODUCT-CAP-005** — kelola kategori (create/edit/delete)

## Entitas & Aturan Kunci

- **PRODUCT-ENT-001 Product** — `sku` (unik), `name`, `categoryId`, `unit`, `purchasePrice`, `sellingPrice`, `reorderPoint` (≥0, DB `CHECK`), `imagePath`, `isActive` (`app/Entity/Product.php`, tabel `products`)
- **PRODUCT-ENT-002 Category** — `name`, `description` (`app/Entity/Category.php`, tabel `categories`); `products.category_id` bersifat `ON DELETE SET NULL`
- Batasan upload gambar (diterapkan di `ProductService`): MIME dibatasi ke JPEG/PNG/WEBP (diverifikasi lewat `mime_content_type`, bukan hanya ekstensi), maksimum 2MB, disimpan di `public/uploads` dengan nama file acak

## API Surface

**Expose**:

| Jenis | Nama / Route | Role | File Utama |
| ---- | ------------ | ---- | ---------- |
| HTTP | `GET/POST /products*`, `GET/PUT /products/{id}`, `DELETE /products/{id}` | Terautentikasi | [`app/Controller/ProductController.php`](../../app/Controller/ProductController.php) |
| HTTP | `GET/POST /categories*`, `GET/PUT /categories/{id}`, `DELETE /categories/{id}` | Terautentikasi | [`app/Controller/CategoryController.php`](../../app/Controller/CategoryController.php) |

**Konsumsi**:

- Modul `inventory` — baris stok per gudang yang ditampilkan di detail produk

## Alur Data

- Create/edit: form submit → `ProductService` memvalidasi field + file gambar opsional → `ProductRepositoryInterface::save` (`MySqlProductRepository`).

## Screens/Pages

| Screen | Route | File Utama | Kapabilitas Terkait |
| ------ | ----- | ---------- | ------------------ |
| List produk | `/products` | [`views/product/index.php`](../../views/product/index.php) | PRODUCT-CAP-001 |
| Detail produk | `/products/{id}` | [`views/product/show.php`](../../views/product/show.php) | PRODUCT-CAP-002 |
| Create produk | `/products/create` | [`views/product/create.php`](../../views/product/create.php) | PRODUCT-CAP-003 |
| Edit produk | `/products/{id}/edit` | [`views/product/edit.php`](../../views/product/edit.php) | PRODUCT-CAP-004 |
| List/create/edit kategori | `/categories*` | [`views/category/`](../../views/category/) | PRODUCT-CAP-005 |

## Dependensi

- **Modul lain**: `inventory` (tampilan stok), `purchase-order`/`sales-order` (mereferensikan produk lewat ID)
- **Eksternal**: filesystem lokal (`public/uploads`) untuk gambar produk

## Cakupan Test

- `tests/Unit/ProductServiceTest.php` — tercakup (memakai `InMemoryProductRepository`)
- CRUD kategori — tidak ditemukan file test khusus

## Gap / Risiko yang Diketahui

- Logic upload gambar hanya diverifikasi lewat pembacaan kode + unit test, belum diuji manual dengan file gambar nyata di berbagai format/ukuran (`docs/quality/tech-debt.md` item 9).
- Tidak ada validasi lintas-field (mis. margin harga jual vs harga beli).

## Change Log

- **2026-09-04**: Versi awal dibuat dari hasil survei kodebase.
