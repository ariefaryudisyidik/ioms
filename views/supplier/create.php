<?php
$pageTitle = 'New Supplier';
include __DIR__ . '/../partials/header.php';
partial('contact-form', ['heading' => $pageTitle, 'action' => '/suppliers', 'cancelHref' => '/suppliers', 'errors' => $errors ?? []]);
include __DIR__ . '/../partials/footer.php';
