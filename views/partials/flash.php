<?php

use App\Core\Session;

$flashSuccess = Session::getFlash('success');
$flashError = Session::getFlash('error');
$flashInfo = Session::getFlash('info');
?>
<?php if ($flashSuccess): ?>
    <div class="alert alert-success" role="status"><?= e($flashSuccess) ?></div>
<?php endif; ?>
<?php if ($flashError): ?>
    <div class="alert alert-error" role="alert"><?= e($flashError) ?></div>
<?php endif; ?>
<?php if ($flashInfo): ?>
    <div class="alert alert-info" role="status"><?= e($flashInfo) ?></div>
<?php endif; ?>
