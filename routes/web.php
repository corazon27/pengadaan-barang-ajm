<?php

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
});
