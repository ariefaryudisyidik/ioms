<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Category;
use PDO;

final class MySqlCategoryRepository implements CategoryRepositoryInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findById(int $id): ?Category
    {
        $stmt = $this->pdo->prepare('SELECT * FROM categories WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? Category::fromRow($row) : null;
    }

    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM categories ORDER BY name ASC');
        $rows = $stmt ? $stmt->fetchAll() : [];

        return array_map(fn ($r) => Category::fromRow($r), $rows);
    }

    public function save(Category $category): Category
    {
        if ($category->id === null) {
            $stmt = $this->pdo->prepare('INSERT INTO categories (name, description) VALUES (?,?)');
            $stmt->execute([$category->name, $category->description]);
            $category->id = (int) $this->pdo->lastInsertId();

            return $category;
        }

        $stmt = $this->pdo->prepare('UPDATE categories SET name=?, description=? WHERE id=?');
        $stmt->execute([$category->name, $category->description, $category->id]);

        return $category;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM categories WHERE id = ?');

        return $stmt->execute([$id]);
    }

    public function isUsedByProducts(int $categoryId): bool
    {
        $stmt = $this->pdo->prepare('SELECT id FROM products WHERE category_id = ? LIMIT 1');
        $stmt->execute([$categoryId]);

        return $stmt->fetch() !== false;
    }
}
