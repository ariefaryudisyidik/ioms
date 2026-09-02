<?php
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
            <div class="field<?= isset($errors['name']) ? ' has-error' : '' ?>">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" data-required value="<?= e($old['name']) ?>" required>
                <?php if (isset($errors['name'])): ?><div class="field-error"><?= e($errors['name']) ?></div><?php endif; ?>
            </div>
            <div class="field<?= isset($errors['email']) ? ' has-error' : '' ?>">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" data-required data-type="email" value="<?= e($old['email']) ?>" required>
                <?php if (isset($errors['email'])): ?><div class="field-error"><?= e($errors['email']) ?></div><?php endif; ?>
            </div>
            <div class="field<?= isset($errors['password']) ? ' has-error' : '' ?>">
                <label for="password">New Password</label>
                <input type="password" id="password" name="password" minlength="8">
                <div class="hint">Leave blank to keep the current password.</div>
                <?php if (isset($errors['password'])): ?><div class="field-error"><?= e($errors['password']) ?></div><?php endif; ?>
            </div>
            <div class="field<?= isset($errors['role']) ? ' has-error' : '' ?>">
                <label for="role">Role</label>
                <select id="role" name="role" data-required required>
                    <?php foreach (['Admin', 'Sales', 'WarehouseStaff'] as $r): ?>
                        <option value="<?= e($r) ?>" <?= $old['role'] === $r ? 'selected' : '' ?>><?= e($r) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['role'])): ?><div class="field-error"><?= e($errors['role']) ?></div><?php endif; ?>
            </div>
        </div>
        <div class="field checkbox-field">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" id="is_active" name="is_active" value="1" <?= $user->isActive ? 'checked' : '' ?>>
            <label for="is_active" style="margin:0;">Active</label>
        </div>
        <div class="btn-row">
            <button type="submit" class="btn">Save</button>
            <a class="btn btn-secondary" href="/users">Cancel</a>
        </div>
    </form>
</div>
<?php include __DIR__ . '/../partials/footer.php'; ?>
