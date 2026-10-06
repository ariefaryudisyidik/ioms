<?php
$hasError = ' has-error';
/** @var \App\Entity\User $user */
$pageTitle = 'Edit User';
$errors = $errors ?? [];
$old = [
    'name' => \App\Core\Session::old('name', $user->name),
    'email' => \App\Core\Session::old('email', $user->email),
    'role' => \App\Core\Session::old('role', $user->role),
];
include __DIR__ . '/../partials/header.php';
?>
<h1>Edit User</h1>
<?php include __DIR__ . '/../partials/form-errors.php'; ?>
<div class="panel">
    <form method="post" action="/users/<?= (int) $user->id ?>" data-validate novalidate>
        <input type="hidden" name="_method" value="PUT">
        <div class="form-grid">
            <div class="field<?= isset($errors['name']) ? $hasError : '' ?>">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" data-required placeholder="Enter full name" value="<?= e($old['name']) ?>" required>
                <?php if (isset($errors['name'])): ?><div class="field-error"><?= e($errors['name']) ?></div><?php endif; ?>
            </div>
            <div class="field<?= isset($errors['email']) ? $hasError : '' ?>">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" data-required data-type="email" placeholder="name@company.com" value="<?= e($old['email']) ?>" required>
                <?php if (isset($errors['email'])): ?><div class="field-error"><?= e($errors['email']) ?></div><?php endif; ?>
            </div>
            <div class="field<?= isset($errors['password']) ? $hasError : '' ?>">
                <label for="password">New Password</label>
                <input type="password" id="password" name="password" minlength="8" placeholder="Leave blank to keep current password">
                <div class="hint">Leave blank to keep the current password.</div>
                <?php if (isset($errors['password'])): ?><div class="field-error"><?= e($errors['password']) ?></div><?php endif; ?>
            </div>
            <div class="field<?= isset($errors['role']) ? $hasError : '' ?>">
                <label for="role">Role</label>
                <select id="role" name="role" data-required required>
                    <?php foreach (['Admin', 'Sales', 'WarehouseStaff'] as $r): ?>
                        <option value="<?= e($r) ?>" <?= $old['role'] === $r ? 'selected' : '' ?>><?= e(roleLabel($r)) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['role'])): ?><div class="field-error"><?= e($errors['role']) ?></div><?php endif; ?>
            </div>
        </div>
        <?php partial('switch-field', ['active' => $user->isActive]); ?>
        <div class="btn-row">
            <button type="submit" class="btn"><?= icon('save') ?>Save</button>
            <a class="btn btn-secondary" href="/users"><?= icon('x') ?>Cancel</a>
        </div>
    </form>
</div>
<?php include __DIR__ . '/../partials/footer.php'; ?>
