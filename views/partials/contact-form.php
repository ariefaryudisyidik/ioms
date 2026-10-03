<?php
/**
 * Name / contact / address form shared by customers and suppliers.
 *
 * @var string $heading
 * @var string $action
 * @var string $cancelHref
 * @var bool|null $isEdit
 * @var bool|null $active
 * @var array<string,string>|null $defaults values used when no old input is flashed
 * @var array<string,string>|null $errors
 */
$defaults ??= ['name' => '', 'contact' => '', 'address' => ''];
$isEdit ??= false;
$active ??= true;
$errors ??= [];
$old = [];
foreach ($defaults as $key => $value) {
    $old[$key] = \App\Core\Session::old($key, $value);
}
?>
<h1><?= e($heading) ?></h1>
<?php include __DIR__ . '/form-errors.php'; ?>
<div class="panel">
    <form method="post" action="<?= e($action) ?>" data-validate novalidate>
        <?php if ($isEdit): ?><input type="hidden" name="_method" value="PUT"><?php endif; ?>
        <?php partial('text-field', ['id' => 'name', 'label' => 'Name', 'type' => 'text', 'value' => $old['name'], 'attrs' => 'data-required', 'required' => true, 'errorKey' => 'name', 'errors' => $errors]); ?>
        <?php partial('text-field', ['id' => 'contact', 'label' => 'Contact', 'type' => 'text', 'value' => $old['contact'], 'attrs' => '', 'required' => false, 'errorKey' => null, 'errors' => $errors]); ?>
        <div class="field">
            <label for="address">Address</label>
            <textarea id="address" name="address" rows="3"><?= e($old['address']) ?></textarea>
        </div>
        <?php partial('form-footer', ['active' => $active, 'cancelHref' => $cancelHref]); ?>
    </form>
</div>
