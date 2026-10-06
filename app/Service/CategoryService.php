<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Category;
use App\Repository\CategoryRepositoryInterface;
use App\Service\Concerns\ListsEntities;
use App\Service\Exception\ValidationException;

final class CategoryService
{
    use ListsEntities;

    public function __construct(private CategoryRepositoryInterface $categories)
    {
    }

    public function all(): array
    {
        return $this->categories->all();
    }

    /** @return array<string, callable(object):mixed> */
    protected function listColumns(): array
    {
        return [
            'name' => static fn (Category $e): string => $e->name,
            'description' => static fn (Category $e): string => (string) $e->description,
        ];
    }

    /** @return list<string> */
    protected function searchColumns(): array
    {
        return ['name', 'description'];
    }

    public function find(int $id): ?Category
    {
        return $this->categories->findById($id);
    }

    public function create(array $data): Category
    {
        $errors = $this->validate($data);
        if ($errors) {
            throw new ValidationException($errors);
        }

        return $this->categories->save(new Category(null, trim($data['name']), $data['description'] ?? null));
    }

    public function update(int $id, array $data): Category
    {
        $category = $this->categories->findById($id);
        if ($category === null) {
            throw new ValidationException(['id' => 'Category not found.']);
        }

        $errors = $this->validate($data);
        if ($errors) {
            throw new ValidationException($errors);
        }

        $category->name = trim($data['name']);
        $category->description = $data['description'] ?? null;

        return $this->categories->save($category);
    }

    public function delete(int $id): bool
    {
        if ($this->categories->isUsedByProducts($id)) {
            throw new ValidationException(['id' => 'Category is used by existing products and cannot be deleted.']);
        }

        return $this->categories->delete($id);
    }

    private function validate(array $data): array
    {
        $errors = [];
        if (trim((string) ($data['name'] ?? '')) === '') {
            $errors['name'] = 'Name is required.';
        }

        return $errors;
    }
}
