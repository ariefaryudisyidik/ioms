<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Controller\ApiController;
use App\Controller\AuthController;
use App\Controller\CategoryController;
use App\Controller\CustomerController;
use App\Controller\DashboardController;
use App\Controller\ProductController;
use App\Controller\PurchaseOrderController;
use App\Controller\ReportController;
use App\Controller\SalesOrderController;
use App\Controller\SupplierController;
use App\Controller\UserController;
use App\Controller\WarehouseController;
use App\Core\Env;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\View;

// Load .env + config (config.php loads Env internally too, but doing it
// once here up-front keeps intent explicit for anyone reading this file).
Env::load(dirname(__DIR__) . '/.env');
$config = require dirname(__DIR__) . '/config/config.php';

// Never leak stack traces to the client (ERR-01); still log everything.
ini_set('display_errors', '0');
error_reporting(E_ALL);

View::setViewsPath(dirname(__DIR__) . '/views');

$router = new Router();

// ---------------------------------------------------------------------
// Auth
// ---------------------------------------------------------------------
$router->get('/', [AuthController::class, 'showLogin']);
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);
$router->get('/logout', [AuthController::class, 'logout']);

// ---------------------------------------------------------------------
// Dashboard
// ---------------------------------------------------------------------
$router->get('/dashboard', [DashboardController::class, 'index']);

// ---------------------------------------------------------------------
// Products
// ---------------------------------------------------------------------
$router->get('/products', [ProductController::class, 'index']);
$router->get('/products/create', [ProductController::class, 'create']);
$router->post('/products', [ProductController::class, 'store']);
$router->get('/products/{id}', [ProductController::class, 'show']);
$router->get('/products/{id}/edit', [ProductController::class, 'edit']);
$router->post('/products/{id}', [ProductController::class, 'update']);
$router->put('/products/{id}', [ProductController::class, 'update']);
$router->post('/products/{id}/delete', [ProductController::class, 'destroy']);
$router->delete('/products/{id}', [ProductController::class, 'destroy']);

// ---------------------------------------------------------------------
// Categories
// ---------------------------------------------------------------------
$router->get('/categories', [CategoryController::class, 'index']);
$router->get('/categories/create', [CategoryController::class, 'create']);
$router->post('/categories', [CategoryController::class, 'store']);
$router->get('/categories/{id}/edit', [CategoryController::class, 'edit']);
$router->post('/categories/{id}', [CategoryController::class, 'update']);
$router->put('/categories/{id}', [CategoryController::class, 'update']);
$router->post('/categories/{id}/delete', [CategoryController::class, 'destroy']);
$router->delete('/categories/{id}', [CategoryController::class, 'destroy']);

// ---------------------------------------------------------------------
// Warehouses
// ---------------------------------------------------------------------
$router->get('/warehouses', [WarehouseController::class, 'index']);
$router->get('/warehouses/create', [WarehouseController::class, 'create']);
$router->post('/warehouses', [WarehouseController::class, 'store']);
$router->get('/warehouses/{id}/edit', [WarehouseController::class, 'edit']);
$router->post('/warehouses/{id}', [WarehouseController::class, 'update']);
$router->put('/warehouses/{id}', [WarehouseController::class, 'update']);
$router->post('/warehouses/{id}/deactivate', [WarehouseController::class, 'deactivate']);

// ---------------------------------------------------------------------
// Suppliers
// ---------------------------------------------------------------------
$router->get('/suppliers', [SupplierController::class, 'index']);
$router->get('/suppliers/create', [SupplierController::class, 'create']);
$router->post('/suppliers', [SupplierController::class, 'store']);
$router->get('/suppliers/{id}/edit', [SupplierController::class, 'edit']);
$router->post('/suppliers/{id}', [SupplierController::class, 'update']);
$router->put('/suppliers/{id}', [SupplierController::class, 'update']);
$router->post('/suppliers/{id}/deactivate', [SupplierController::class, 'deactivate']);

// ---------------------------------------------------------------------
// Customers
// ---------------------------------------------------------------------
$router->get('/customers', [CustomerController::class, 'index']);
$router->get('/customers/create', [CustomerController::class, 'create']);
$router->post('/customers', [CustomerController::class, 'store']);
$router->get('/customers/{id}/edit', [CustomerController::class, 'edit']);
$router->post('/customers/{id}', [CustomerController::class, 'update']);
$router->put('/customers/{id}', [CustomerController::class, 'update']);
$router->post('/customers/{id}/deactivate', [CustomerController::class, 'deactivate']);

// ---------------------------------------------------------------------
// Purchase Orders
// ---------------------------------------------------------------------
$router->get('/purchase-orders', [PurchaseOrderController::class, 'index']);
$router->get('/purchase-orders/create', [PurchaseOrderController::class, 'create']);
$router->post('/purchase-orders', [PurchaseOrderController::class, 'store']);
$router->get('/purchase-orders/{id}', [PurchaseOrderController::class, 'show']);
$router->post('/purchase-orders/{id}/order', [PurchaseOrderController::class, 'order']);
$router->post('/purchase-orders/{id}/cancel', [PurchaseOrderController::class, 'cancel']);
$router->get('/purchase-orders/{id}/receive', [PurchaseOrderController::class, 'receiveForm']);
$router->post('/purchase-orders/{id}/receive', [PurchaseOrderController::class, 'receive']);

// ---------------------------------------------------------------------
// Sales Orders
// ---------------------------------------------------------------------
$router->get('/sales-orders', [SalesOrderController::class, 'index']);
$router->get('/sales-orders/create', [SalesOrderController::class, 'create']);
$router->post('/sales-orders', [SalesOrderController::class, 'store']);
$router->get('/sales-orders/{id}', [SalesOrderController::class, 'show']);
$router->post('/sales-orders/{id}/submit', [SalesOrderController::class, 'submit']);
$router->post('/sales-orders/{id}/approve', [SalesOrderController::class, 'approve']);
$router->post('/sales-orders/{id}/reject', [SalesOrderController::class, 'reject']);
$router->post('/sales-orders/{id}/fulfill', [SalesOrderController::class, 'fulfill']);
$router->post('/sales-orders/{id}/cancel', [SalesOrderController::class, 'cancel']);

// ---------------------------------------------------------------------
// Users (Admin only, enforced in the controller)
// ---------------------------------------------------------------------
$router->get('/users', [UserController::class, 'index']);
$router->get('/users/create', [UserController::class, 'create']);
$router->post('/users', [UserController::class, 'store']);
$router->get('/users/{id}/edit', [UserController::class, 'edit']);
$router->post('/users/{id}', [UserController::class, 'update']);
$router->put('/users/{id}', [UserController::class, 'update']);
$router->post('/users/{id}/deactivate', [UserController::class, 'deactivate']);

// ---------------------------------------------------------------------
// Reports
// ---------------------------------------------------------------------
$router->get('/reports', [ReportController::class, 'index']);
$router->get('/reports/stock-ledger.csv', [ReportController::class, 'stockLedgerCsv']);
$router->get('/reports/orders.csv', [ReportController::class, 'orderStatusCsv']);

// ---------------------------------------------------------------------
// JSON API
// ---------------------------------------------------------------------
$router->get('/api/products/{sku}/availability', [ApiController::class, 'productAvailability']);

// ---------------------------------------------------------------------
// 404 fallback
// ---------------------------------------------------------------------
$router->setNotFoundHandler(static function (Request $request) {
    $path = $request->path();
    if (str_starts_with($path, '/api/')) {
        Response::notFound();

        return;
    }
    View::display('errors.404', [], 404);
});

// ---------------------------------------------------------------------
// Dispatch, with a hard safety net so an uncaught exception never leaks a
// stack trace to the client (ERR-01). Controller::handle() already covers
// the normal action bodies; this is the outermost fallback for anything
// thrown before/after that (routing, bootstrap, etc).
// ---------------------------------------------------------------------
try {
    $router->dispatch(Request::capture());
} catch (\Throwable $e) {
    error_log('[Unhandled/bootstrap] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

    $path = Request::capture()->path();
    if (str_starts_with($path, '/api/')) {
        Response::json(['error' => 'An unexpected error occurred.'], 500);
    } else {
        View::display('errors.500', [], 500);
    }
}
