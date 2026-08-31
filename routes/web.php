<?php

use App\Http\Controllers\CatalogController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/katalog', [CatalogController::class, 'index']);

Route::get('/', function () {
    $canonicalUrl = app()->environment('production') && config('app.public_url')
        ? rtrim((string) config('app.public_url'), '/')
        : null;

    return Inertia::render('Home', $canonicalUrl !== null
        ? ['canonicalUrl' => $canonicalUrl]
        : []);
});
