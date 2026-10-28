<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CashController;
use App\Http\Controllers\CourtController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.store');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::prefix('bookings')->name('bookings.')->group(function () {
        Route::get('/', [BookingController::class, 'index'])->name('index');
        Route::get('/quote', [BookingController::class, 'quote'])->name('quote');
        Route::post('/', [BookingController::class, 'store'])->name('store');
        Route::post('/{booking}/payment', [BookingController::class, 'payment'])->middleware('role:owner,admin,kasir')->name('payment');
        Route::post('/{booking}/confirm', [BookingController::class, 'confirm'])->name('confirm');
        Route::post('/{booking}/cancel', [BookingController::class, 'cancel'])->name('cancel');
        Route::post('/{booking}/check-in', [BookingController::class, 'checkIn'])->name('checkin');
        Route::post('/{booking}/check-out', [BookingController::class, 'checkOut'])->name('checkout');
        Route::post('/{booking}/no-show', [BookingController::class, 'noShow'])->name('noshow');
        Route::get('/{booking}/proof', [BookingController::class, 'proof'])->name('proof');
        Route::post('/recurring', [BookingController::class, 'recurringStore'])->name('recurring.store');
        Route::post('/recurring/{recurring}/stop', [BookingController::class, 'recurringStop'])->name('recurring.stop');
    });

    Route::get('/courts', [CourtController::class, 'index'])->name('courts.index');
    Route::middleware('role:owner,admin')->group(function () {
        Route::post('/courts', [CourtController::class, 'store'])->name('courts.store');
        Route::put('/courts/{court}', [CourtController::class, 'update'])->name('courts.update');
        Route::post('/courts/{court}/toggle', [CourtController::class, 'toggle'])->name('courts.toggle');
        Route::post('/courts/holidays', [CourtController::class, 'storeHoliday'])->name('courts.holidays.store');
        Route::delete('/courts/holidays/{holiday}', [CourtController::class, 'destroyHoliday'])->name('courts.holidays.destroy');
    });

    Route::middleware('role:owner')->group(function () {
        Route::post('/courts/price-rules', [CourtController::class, 'storePriceRule'])->name('courts.price-rules.store');
        Route::delete('/courts/price-rules/{rule}', [CourtController::class, 'destroyPriceRule'])->name('courts.price-rules.destroy');
        Route::post('/courts/settings', [CourtController::class, 'updateSettings'])->name('courts.settings.update');
    });

    Route::get('/members', [MemberController::class, 'index'])->middleware('role:owner,admin,kasir')->name('members.index');
    Route::middleware('role:owner,admin,kasir')->group(function () {
        Route::post('/members', [MemberController::class, 'store'])->name('members.store');
        Route::post('/members/{member}/renew', [MemberController::class, 'renew'])->name('members.renew');
        Route::post('/members/{member}/topup', [MemberController::class, 'topup'])->name('members.topup');
        Route::post('/members/{member}/reset-noshow', [MemberController::class, 'resetNoShow'])->name('members.reset-noshow');
    });

    Route::middleware('role:owner,admin,kasir')->prefix('pos')->name('pos.')->group(function () {
        Route::get('/', [PosController::class, 'index'])->name('index');
        Route::post('/checkout', [PosController::class, 'checkout'])->name('checkout');
        Route::get('/history', [PosController::class, 'history'])->name('history');
        Route::get('/{sale}/receipt', [PosController::class, 'receipt'])->name('receipt');
        Route::post('/{sale}/void', [PosController::class, 'void'])->name('void');
    });

    Route::prefix('cash')->name('cash.')->group(function () {
        Route::get('/', [CashController::class, 'index'])->name('index');
        Route::post('/open', [CashController::class, 'open'])->name('open');
        Route::post('/close', [CashController::class, 'close'])->name('close');
        Route::post('/movement', [CashController::class, 'movement'])->name('movement');
        Route::post('/deposit', [CashController::class, 'deposit'])->middleware('role:owner,admin,kasir')->name('deposit');
    });

    Route::middleware('role:owner,admin')->prefix('purchase-orders')->name('purchase-orders.')->group(function () {
        Route::get('/', [PurchaseOrderController::class, 'index'])->name('index');
        Route::post('/', [PurchaseOrderController::class, 'store'])->name('store');
        Route::post('/{po}/approve', [PurchaseOrderController::class, 'approve'])->name('approve');
        Route::post('/{po}/reject', [PurchaseOrderController::class, 'reject'])->name('reject');
        Route::post('/{po}/receive', [PurchaseOrderController::class, 'receive'])->name('receive');
        Route::post('/expenses', [PurchaseOrderController::class, 'expenseStore'])->name('expenses.store');
        Route::post('/recurring', [PurchaseOrderController::class, 'recurringStore'])->name('recurring.store');
        Route::post('/suppliers', [PurchaseOrderController::class, 'supplierStore'])->name('suppliers.store');
    });

    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::middleware('role:owner,admin')->group(function () {
        Route::post('/inventory/products', [InventoryController::class, 'storeProduct'])->name('inventory.products.store');
        Route::put('/inventory/products/{product}', [InventoryController::class, 'updateProduct'])->name('inventory.products.update');
        Route::post('/inventory/stock-in', [InventoryController::class, 'stockIn'])->name('inventory.stock-in');
        Route::post('/inventory/stock-out', [InventoryController::class, 'stockOut'])->name('inventory.stock-out');
        Route::post('/inventory/opname', [InventoryController::class, 'opname'])->name('inventory.opname');
    });

    Route::middleware('role:owner,admin')->get('/reports', [ReportController::class, 'index'])->name('reports.index');

    Route::middleware('role:owner,admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/audit', [AdminController::class, 'audit'])->name('audit');
    });

    Route::middleware('role:owner')->group(function () {
        Route::get('/admin/users', [AdminController::class, 'users'])->name('admin.users');
        Route::post('/admin/users', [AdminController::class, 'storeUser'])->name('admin.users.store');
        Route::put('/admin/users/{user}', [AdminController::class, 'updateUser'])->name('admin.users.update');
        Route::get('/admin/settings', [AdminController::class, 'settings'])->name('admin.settings');
        Route::post('/admin/settings', [AdminController::class, 'updateSettings'])->name('admin.settings.update');
    });
});
