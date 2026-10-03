<?php
$pageTitle = 'New Customer';
include __DIR__ . '/../partials/header.php';
partial('contact-form', ['heading' => $pageTitle, 'action' => '/customers', 'cancelHref' => '/customers', 'errors' => $errors ?? []]);
include __DIR__ . '/../partials/footer.php';
