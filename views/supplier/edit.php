<?php
/** @var \App\Entity\Supplier $supplier */
$pageTitle = 'Edit Supplier';
include __DIR__ . '/../partials/header.php';
partial('contact-form', [
    'heading' => $pageTitle,
    'action' => '/suppliers/' . (int) $supplier->id,
    'cancelHref' => '/suppliers',
    'isEdit' => true,
    'active' => $supplier->isActive,
    'defaults' => ['name' => $supplier->name, 'contact' => $supplier->contact ?? '', 'address' => $supplier->address ?? ''],
    'errors' => $errors ?? [],
]);
include __DIR__ . '/../partials/footer.php';
