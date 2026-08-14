<?php

use App\Http\Controllers\Api\Analytics\AnalyticsController;
use App\Http\Controllers\Api\AuditLog\AuditLogController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\ProfileController;
use App\Http\Controllers\Api\Bast\BastController;
use App\Http\Controllers\Api\Document\DocumentController;
use App\Http\Controllers\Api\Invoice\InvoiceController;
use App\Http\Controllers\Api\Notification\NotificationController;
use App\Http\Controllers\Api\Order\OrderController;
use App\Http\Controllers\Api\Payment\PaymentController;
use App\Http\Controllers\Api\Product\ProductController;
use App\Http\Controllers\Api\Rfq\RfqController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you may register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is guarded by the "api" middleware group. Enjoy!
|
*/

Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::put('/auth/profile', [ProfileController::class, 'update']);
});

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you may register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is guarded by the "api" middleware group. Enjoy!
|
*/

Route::get('/products', [ProductController::class, 'index']); // Public index
Route::get('/products/{product}', [ProductController::class, 'show']); // Public show

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{product}', [ProductController::class, 'update']);
    Route::delete('/products/{product}', [ProductController::class, 'destroy']);
});

/*
|--------------------------------------------------------------------------
| RFQ Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/rfqs', [RfqController::class, 'index']);
    Route::post('/rfqs', [RfqController::class, 'store']);
    Route::get('/rfqs/{rfq}', [RfqController::class, 'show']);
    Route::post('/rfqs/{rfq}/respond', [RfqController::class, 'respond']);
    Route::patch('/rfqs/{rfq}/status', [RfqController::class, 'updateStatus']);
});

/*
|--------------------------------------------------------------------------
| Order Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus']);

    Route::get('/orders/{order}/bast', [BastController::class, 'show']);
    Route::post('/orders/{order}/bast/sign', [BastController::class, 'sign']);
});

/*
|--------------------------------------------------------------------------
| Invoice Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/invoices', [InvoiceController::class, 'index']);
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show']);
    Route::patch('/invoices/{invoice}/payment-status', [InvoiceController::class, 'updatePaymentStatus']);
});

/*
|--------------------------------------------------------------------------
| Document Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/rfqs/{rfq}/quotation.pdf', [DocumentController::class, 'quotationPdf']);
    Route::get('/orders/{order}/bast.pdf', [DocumentController::class, 'bastPdf']);
    Route::get('/invoices/{invoice}/pdf', [DocumentController::class, 'invoicePdf']);
});

/*
|--------------------------------------------------------------------------
| Payment Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/invoices/{invoice}/payments', [PaymentController::class, 'store']);
    Route::get('/payments', [PaymentController::class, 'index']);
    Route::get('/payments/{payment}/proof', [PaymentController::class, 'downloadProof']);
    Route::patch('/payments/{payment}/verify', [PaymentController::class, 'verify']);
});

/*
|--------------------------------------------------------------------------
| Notification Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll']);
});

/*
|--------------------------------------------------------------------------
| Audit Log & Analytics Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/audit-logs', [AuditLogController::class, 'index']);
    Route::get('/analytics/dashboard', [AnalyticsController::class, 'dashboard']);
});
