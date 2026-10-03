<?php
/** @var \App\Entity\Customer $customer */
$pageTitle = 'Edit Customer';
include __DIR__ . '/../partials/header.php';
partial('contact-form', [
    'heading' => $pageTitle,
    'action' => '/customers/' . (int) $customer->id,
    'cancelHref' => '/customers',
    'isEdit' => true,
    'active' => $customer->isActive,
    'defaults' => ['name' => $customer->name, 'contact' => $customer->contact ?? '', 'address' => $customer->address ?? ''],
    'errors' => $errors ?? [],
]);
include __DIR__ . '/../partials/footer.php';
