<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Product;
use App\Repository\CategoryRepositoryInterface;
use App\Repository\ProductRepositoryInterface;
use App\Service\Exception\ValidationException;

final class ProductService
{
    private const EXTENSIONS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    private const MAX_BYTES = 2 * 1024 * 1024; // 2MB

    public function __construct(
        private ProductRepositoryInterface $products,
        private CategoryRepositoryInterface $categories,
        private string $uploadDir = 'public/uploads',
    ) {
    }

    public function find(int $id): ?Product
    {
        return $this->products->findById($id);
    }

    public function findBySku(string $sku): ?Product
    {
        return $this->products->findBySku($sku);
    }

    /**
     * @param array{search?:string,category_id?:int,status?:string,sort?:string} $filters
     */
    public function paginate(array $filters, int $page, int $perPage = 10): array
    {
        $page = max(1, $page);
        $filters['limit'] = $perPage;
        $filters['offset'] = ($page - 1) * $perPage;

        $items = $this->products->search($filters);
        $total = $this->products->countSearch($filters);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => (int) max(1, ceil($total / $perPage)),
        ];
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed>|null $file $_FILES['image'] entry
     */
    public function create(array $data, ?array $file = null): Product
    {
        $errors = $this->validate($data, null);
        if ($errors) {
            throw new ValidationException($errors);
        }

        $imagePath = null;
        if ($file !== null && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $imagePath = $this->storeImage($file);
        }

        $product = new Product(
            id: null,
            sku: trim($data['sku']),
            name: trim($data['name']),
            categoryId: (int) $data['category_id'],
            unit: trim((string) ($data['unit'] ?? 'pcs')),
            purchasePrice: (float) $data['purchase_price'],
            sellingPrice: (float) $data['selling_price'],
            reorderPoint: (int) $data['reorder_point'],
            imagePath: $imagePath,
            isActive: (bool) ($data['is_active'] ?? true),
        );

        return $this->products->save($product);
    }

    public function update(int $id, array $data, ?array $file = null): Product
    {
        $product = $this->products->findById($id);
        if ($product === null) {
            throw new ValidationException(['id' => 'Product not found.']);
        }

        $errors = $this->validate($data, $id);
        if ($errors) {
            throw new ValidationException($errors);
        }

        if ($file !== null && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $product->imagePath = $this->storeImage($file);
        }

        $product->sku = trim($data['sku']);
        $product->name = trim($data['name']);
        $product->categoryId = (int) $data['category_id'];
        $product->unit = trim((string) ($data['unit'] ?? $product->unit));
        $product->purchasePrice = (float) $data['purchase_price'];
        $product->sellingPrice = (float) $data['selling_price'];
        $product->reorderPoint = (int) $data['reorder_point'];
        $product->isActive = (bool) ($data['is_active'] ?? $product->isActive);

        return $this->products->save($product);
    }

    /**
     * Attempt a hard delete; falls back to a soft deactivate when the
     * product is referenced by existing purchase/sales order items.
     *
     * @return bool true if hard-deleted, false if only deactivated
     */
    public function delete(int $id): bool
    {
        $product = $this->products->findById($id);
        if ($product === null) {
            throw new ValidationException(['id' => 'Product not found.']);
        }

        if ($this->products->isUsedInOrders($id)) {
            $product->isActive = false;
            $this->products->save($product);

            return false;
        }

        // No hard-delete method is exposed to keep referential integrity
        // simple; deactivating is always safe and sufficient for this app.
        $product->isActive = false;
        $this->products->save($product);

        return true;
    }

    /**
     * @param array<string,mixed> $file
     */
    private function storeImage(array $file): string
    {
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new ValidationException(['image' => 'Image upload failed.']);
        }

        if (($file['size'] ?? 0) > self::MAX_BYTES) {
            throw new ValidationException(['image' => 'Image must be at most 2MB.']);
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        $mime = @mime_content_type($tmpName) ?: '';
        // The declared type must match a real, decodable image (rejects polyglot/garbage payloads).
        if (!isset(self::EXTENSIONS[$mime]) || @getimagesize($tmpName) === false) {
            throw new ValidationException(['image' => 'Image must be JPEG, PNG, or WEBP.']);
        }

        $filename = bin2hex(random_bytes(16)) . '.' . self::EXTENSIONS[$mime];
        $destDir = rtrim($this->uploadDir, '/');
        if (!is_dir($destDir)) {
            mkdir($destDir, 0775, true);
        }
        $destination = $destDir . '/' . $filename;

        if (!move_uploaded_file($tmpName, $destination) && !copy($tmpName, $destination)) {
            throw new ValidationException(['image' => 'Failed to store the uploaded image.']);
        }

        return $filename;
    }

    /**
     * @return array<string,string>
     */
    private function validate(array $data, ?int $excludeId): array
    {
        $errors = [];

        $sku = trim((string) ($data['sku'] ?? ''));
        if ($sku === '') {
            $errors['sku'] = 'SKU is required.';
        } elseif ($this->products->skuExists($sku, $excludeId)) {
            $errors['sku'] = 'This SKU already exists.';
        }

        if (trim((string) ($data['name'] ?? '')) === '') {
            $errors['name'] = 'Name is required.';
        }

        $categoryId = (int) ($data['category_id'] ?? 0);
        if ($categoryId <= 0 || $this->categories->findById($categoryId) === null) {
            $errors['category_id'] = 'A valid category is required.';
        }

        if (!is_numeric($data['purchase_price'] ?? null) || (float) $data['purchase_price'] < 0) {
            $errors['purchase_price'] = 'Purchase price must be a non-negative number.';
        }

        if (!is_numeric($data['selling_price'] ?? null) || (float) $data['selling_price'] < 0) {
            $errors['selling_price'] = 'Selling price must be a non-negative number.';
        }

        if (!is_numeric($data['reorder_point'] ?? null) || (int) $data['reorder_point'] < 0) {
            $errors['reorder_point'] = 'Reorder point must be a non-negative integer.';
        }

        return $errors;
    }
}
