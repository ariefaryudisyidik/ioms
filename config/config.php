<?php

declare(strict_types=1);

use App\Core\Env;

Env::load(dirname(__DIR__) . '/.env');

return [
    'app' => [
        'name' => Env::get('APP_NAME', 'Inventory & Order Management System'),
        'env' => Env::get('APP_ENV', 'production'),
        'url' => Env::get('APP_URL', 'http://localhost:8000'),
        'debug' => (bool) Env::get('APP_DEBUG', false),
        'upload_dir' => Env::get('APP_UPLOAD_DIR', 'public/uploads'),
    ],
    'db' => [
        'host' => Env::get('DB_HOST', '127.0.0.1'),
        'port' => Env::get('DB_PORT', '3306'),
        'database' => Env::get('DB_DATABASE', 'ioms'),
        'username' => Env::get('DB_USERNAME', 'root'),
        'password' => Env::get('DB_PASSWORD', ''),
    ],
    'session' => [
        'name' => Env::get('SESSION_NAME', 'ioms_session'),
    ],
];
