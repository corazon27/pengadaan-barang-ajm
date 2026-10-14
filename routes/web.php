<?php

use App\Http\Controllers\Account\HomeController as AccountHomeController;
use App\Http\Controllers\Account\InvoiceController as AccountInvoiceController;
use App\Http\Controllers\Account\NotificationController as AccountNotificationController;
use App\Http\Controllers\Account\OrderController as AccountOrderController;
use App\Http\Controllers\Account\RfqController as AccountRfqController;
use App\Http\Controllers\Admin\AccountReviewController;
use App\Http\Controllers\Admin\B2gEvidenceController;
use App\Http\Controllers\Auth\AccountStatusController;
use App\Http\Controllers\Auth\ApplicationEvidenceController;
use App\Http\Controllers\Auth\ApplicationResubmitController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmPasswordController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\ProductDetailController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/katalog', [CatalogController::class, 'index'])
    ->middleware('throttle:public-web');

// 3C.6 â€” public product detail. `/katalog` is the collection/discovery surface;
// `/produk/{sku}` is the individual product resource. The identifier is the SKU
// (already part of the 3C.5 public allowlist, so the catalog card can link here
// with no payload change). No `where()` constraint: the schema defines no SKU
// character contract, and the default single-segment pattern already makes a SKU
// containing "/" unaddressable. Public and LOCKED â€” no middleware, no policy.
Route::get('/produk/{sku}', [ProductDetailController::class, 'show'])
    ->middleware('throttle:public-web')
    ->name('produk.show');

Route::get('/cara-order', function () {
    $canonicalUrl = app()->environment('production') && config('app.public_url')
        ? rtrim((string) config('app.public_url'), '/').'/cara-order'
        : null;

    return Inertia::render('CaraOrder', $canonicalUrl !== null
        ? ['canonicalUrl' => $canonicalUrl]
        : []);
})->name('cara-order.show');

Route::get('/', function () {
    $canonicalUrl = app()->environment('production') && config('app.public_url')
        ? rtrim((string) config('app.public_url'), '/')
        : null;

    return Inertia::render('Home', $canonicalUrl !== null
        ? ['canonicalUrl' => $canonicalUrl]
        : []);
})->name('home');

// --- Authentication (guest) -------------------------------------------------
Route::middleware('guest')->group(function (): void {
    Route::get('/masuk', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/masuk', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.store');

    Route::get('/daftar', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/daftar', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:register')
        ->name('register.store');

    Route::get('/lupa-kata-sandi', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/lupa-kata-sandi', [PasswordResetController::class, 'email'])
        ->middleware('throttle:password.email')
        ->name('password.email');
    Route::get('/atur-ulang-kata-sandi/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('/atur-ulang-kata-sandi', [PasswordResetController::class, 'store'])
        ->middleware('throttle:password.reset')
        ->name('password.store');
});

// --- Signed, expiring guest links (no session, no token) --------------------
Route::middleware('signed')->group(function (): void {
    Route::get('/verifikasi-email/{user}/{hash}', [EmailVerificationController::class, 'show'])
        ->name('verification.show');
    Route::post('/verifikasi-email/{user}/{hash}', [EmailVerificationController::class, 'verify'])
        ->name('verification.verify');

    Route::get('/status-akun/{user}', [AccountStatusController::class, 'show'])
        ->middleware('throttle:account-status')
        ->name('account.status');

    Route::get('/pengajuan-ulang/{user}', [ApplicationResubmitController::class, 'show'])->name('resubmit.show');
    Route::post('/pengajuan-ulang/{user}', [ApplicationResubmitController::class, 'store'])
        ->middleware('throttle:resubmit')
        ->name('resubmit.store');

    Route::get('/bukti-b2g/{user}', [ApplicationEvidenceController::class, 'show'])->name('application.evidence.show');
    Route::post('/bukti-b2g/{user}', [ApplicationEvidenceController::class, 'store'])
        ->middleware('throttle:resubmit')
        ->name('application.evidence.store');
});

Route::post('/verifikasi-email/kirim-ulang', [EmailVerificationController::class, 'resend'])
    ->middleware('throttle:email-verification')
    ->name('verification.resend');

// --- Authenticated account --------------------------------------------------
Route::middleware(['auth', 'account.active'])->group(function (): void {
    Route::get('/akun', [AccountHomeController::class, 'index'])->name('account.home');
    Route::post('/keluar', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    // Buyer read-only self-service surfaces. Every action is authorized through
    // the ownership-aware policies; listings are additionally scoped via the
    // models' `visibleTo` query scope. Read-only: no buyer mutations.
    Route::get('/akun/pesanan', [AccountOrderController::class, 'index'])->name('account.orders.index');
    Route::get('/akun/pesanan/{order}', [AccountOrderController::class, 'show'])->name('account.orders.show');
    Route::get('/akun/rfq', [AccountRfqController::class, 'index'])->name('account.rfqs.index');
    Route::get('/akun/rfq/{rfq}', [AccountRfqController::class, 'show'])->name('account.rfqs.show');
    Route::get('/akun/tagihan', [AccountInvoiceController::class, 'index'])->name('account.invoices.index');
    Route::get('/akun/tagihan/{invoice}', [AccountInvoiceController::class, 'show'])->name('account.invoices.show');
    Route::get('/akun/notifikasi', [AccountNotificationController::class, 'index'])->name('account.notifications.index');

    Route::get('/konfirmasi-kata-sandi', [ConfirmPasswordController::class, 'show'])->name('password.confirm');
    Route::post('/konfirmasi-kata-sandi', [ConfirmPasswordController::class, 'store'])->name('password.confirm.store');
});

// --- Admin (SUPERADMIN, authorized via UserPolicy) --------------------------
Route::middleware(['auth', 'account.active'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::get('/akun', [AccountReviewController::class, 'index'])->name('accounts.index');
        Route::get('/akun/{account}', [AccountReviewController::class, 'show'])->name('accounts.show');

        Route::post('/akun/{account}/setujui', [AccountReviewController::class, 'approve'])
            ->middleware('password.confirm')
            ->name('accounts.approve');
        Route::post('/akun/{account}/tolak', [AccountReviewController::class, 'reject'])
            ->middleware('password.confirm')
            ->name('accounts.reject');
        Route::post('/akun/{account}/tangguhkan', [AccountReviewController::class, 'suspend'])
            ->middleware('password.confirm')
            ->name('accounts.suspend');
        Route::post('/akun/{account}/aktifkan', [AccountReviewController::class, 'reactivate'])
            ->middleware('password.confirm')
            ->name('accounts.reactivate');

        Route::get('/bukti/{submission}/unduh', [B2gEvidenceController::class, 'download'])
            ->name('evidence.download');
        Route::post('/bukti/{submission}/tinjau', [AccountReviewController::class, 'reviewEvidence'])
            ->middleware('password.confirm')
            ->name('evidence.review');
    });
