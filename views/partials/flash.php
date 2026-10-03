<?php

use App\Core\Session;

$flashSuccess = Session::getFlash('success');
$flashError = Session::getFlash('error');
$flashInfo = Session::getFlash('info');
?>
<?php if ($flashSuccess): ?>
    <output class="alert alert-success"><?= e($flashSuccess) ?></output>
<?php endif; ?>
<?php if ($flashError): ?>
    <div class="alert alert-error" role="alert"><?= e($flashError) ?></div>
<?php endif; ?>
<?php if ($flashInfo): ?>
    <output class="alert alert-info"><?= e($flashInfo) ?></output>
<?php endif; ?>
