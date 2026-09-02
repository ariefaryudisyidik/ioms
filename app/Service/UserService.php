<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepositoryInterface;
use App\Service\Exception\ValidationException;

/**
 * Only meant to be invoked by controllers that have already enforced the
 * Admin role.
 */
final class UserService
{
    private const ROLES = ['Admin', 'Sales', 'WarehouseStaff'];

    public function __construct(private UserRepositoryInterface $users)
    {
    }

    public function all(): array
    {
        return $this->users->all();
    }

    public function find(int $id): ?User
    {
        return $this->users->findById($id);
    }

    /**
     * @param array{name:string,email:string,password:string,role:string,is_active?:bool} $data
     */
    public function create(array $data): User
    {
        $errors = $this->validate($data, true);
        if ($errors) {
            throw new ValidationException($errors);
        }

        $user = new User(
            id: null,
            name: trim($data['name']),
            email: strtolower(trim($data['email'])),
            passwordHash: password_hash($data['password'], PASSWORD_BCRYPT),
            role: $data['role'],
            isActive: (bool) ($data['is_active'] ?? true),
        );

        return $this->users->save($user);
    }

    /**
     * @param array{name:string,email:string,password?:string,role:string,is_active?:bool} $data
     */
    public function update(int $id, array $data): User
    {
        $user = $this->users->findById($id);
        if ($user === null) {
            throw new ValidationException(['id' => 'User not found.']);
        }

        $errors = $this->validate($data, false, $id);
        if ($errors) {
            throw new ValidationException($errors);
        }

        $user->name = trim($data['name']);
        $user->email = strtolower(trim($data['email']));
        $user->role = $data['role'];
        $user->isActive = (bool) ($data['is_active'] ?? $user->isActive);

        if (!empty($data['password'])) {
            $user->passwordHash = password_hash($data['password'], PASSWORD_BCRYPT);
        }

        return $this->users->save($user);
    }

    public function deactivate(int $id): void
    {
        $user = $this->users->findById($id);
        if ($user === null) {
            throw new ValidationException(['id' => 'User not found.']);
        }
        $user->isActive = false;
        $this->users->save($user);
    }

    /**
     * @return array<string,string>
     */
    private function validate(array $data, bool $isCreate, ?int $excludeId = null): array
    {
        $errors = [];

        if (empty(trim((string) ($data['name'] ?? '')))) {
            $errors['name'] = 'Name is required.';
        }

        $email = strtolower(trim((string) ($data['email'] ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'A valid email is required.';
        } elseif ($this->users->emailExists($email, $excludeId)) {
            $errors['email'] = 'This email is already registered.';
        }

        if ($isCreate && empty($data['password'])) {
            $errors['password'] = 'Password is required.';
        } elseif (!empty($data['password']) && strlen((string) $data['password']) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        }

        $role = (string) ($data['role'] ?? '');
        if (!in_array($role, self::ROLES, true)) {
            $errors['role'] = 'A valid role is required.';
        }

        return $errors;
    }
}
