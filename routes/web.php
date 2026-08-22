<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ShopSettingController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));

// Auth (minggu 7 — autentikasi & otorisasi)
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login');
});
Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/search', SearchController::class)->name('search');

    // Kasir POS (increment 2-3-6-7: routing, view, validasi, auth)
    Route::get('/pos', [TransactionController::class, 'create'])->name('pos.create');
    Route::get('/pos/lookup', [TransactionController::class, 'lookup'])->name('pos.lookup');
    Route::post('/pos', [TransactionController::class, 'store'])->name('transactions.store');
    Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
    Route::get('/transactions/{transaction}', [TransactionController::class, 'show'])->name('transactions.show');
    Route::get('/transactions/{transaction}/pdf', [TransactionController::class, 'pdf'])->name('transactions.pdf');
    Route::patch('/transactions/{transaction}/void', [TransactionController::class, 'void'])
        ->name('transactions.void')->middleware('role:admin');

    // Admin only (minggu 7 — otorisasi berbasis peran)
    Route::middleware('role:admin')->group(function () {
        // Bulk routes registered before their matching resource() so a static
        // "bulk-*" segment isn't swallowed by the resource's {category}/{product}
        // wildcard route (same URI shape, resource wins if registered first).
        Route::delete('/categories/bulk-delete', [CategoryController::class, 'bulkDelete'])->name('categories.bulk-delete');
        Route::resource('categories', CategoryController::class)->except('show');

        Route::patch('/products/bulk-status', [ProductController::class, 'bulkStatus'])->name('products.bulk-status');
        Route::resource('products', ProductController::class)->except(['show', 'destroy']);
        Route::patch('/products/{product}/status', [ProductController::class, 'toggleStatus'])->name('products.toggle');
        Route::patch('/products/{product}/stock', [ProductController::class, 'adjustStock'])->name('products.stock.adjust');

        // Laporan & import/export (minggu 9)
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
        Route::get('/reports/export-pdf', [ReportController::class, 'exportPdf'])->name('reports.export.pdf');
        Route::get('/reports/import', [ReportController::class, 'importForm'])->name('reports.import.form');
        Route::post('/reports/import', [ReportController::class, 'import'])->name('reports.import');

        // Manajemen pengguna (minggu 7 — RBAC)
        Route::patch('/users/bulk-status', [UserController::class, 'bulkStatus'])->name('users.bulk-status');
        Route::resource('users', UserController::class)->except(['show', 'destroy']);
        Route::patch('/users/{user}/status', [UserController::class, 'toggleStatus'])->name('users.toggle');

        // Pengaturan toko (branding struk/laporan)
        Route::get('/settings', [ShopSettingController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [ShopSettingController::class, 'update'])->name('settings.update');
    });
});
