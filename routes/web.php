<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MidtransWebhookController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\XenditWebhookController;
use App\Livewire\CashierTerminal;
use App\Livewire\CustomerMenu;
use App\Livewire\KitchenBoard;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Posme — rute utama
|--------------------------------------------------------------------------
| Customer  : publik lewat QR (signed URL), tanpa login.
| Staf      : login + role (admin / cashier / kitchen).
| Webhook   : Midtrans & Xendit server-to-server, tanpa CSRF
|             (verifikasi signature / callback token di controller).
*/

Route::get('/', HomeController::class)->name('home');

// Auth staf
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login')->middleware('guest');
Route::post('/login', [AuthController::class, 'login'])->middleware('guest');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Customer: halaman menu per meja — HANYA lewat signed URL dari QR.
// Tanpa signature yang valid, Laravel menolak (403) sehingga URL meja
// lain tidak bisa ditebak.
Route::get('/m/{table:code}', CustomerMenu::class)
    ->middleware('signed')
    ->name('customer.menu');

// Dapur: board order live (KDS)
Route::get('/kitchen', KitchenBoard::class)
    ->middleware(['auth', 'role:kitchen,admin'])
    ->name('kitchen');

// Kasir (merangkap waiter): monitor order + terminal bayar + riwayat
Route::middleware(['auth', 'role:cashier,admin'])->group(function () {
    Route::get('/cashier', CashierTerminal::class)->name('cashier');
    Route::get('/cashier/receipt/{order}', [ReceiptController::class, 'show'])->name('cashier.receipt');
});

// Webhook Midtrans — signature_key diverifikasi di controller.
Route::post('/midtrans/notification', [MidtransWebhookController::class, 'handle'])
    ->name('midtrans.notification');

// Webhook Xendit — header x-callback-token diverifikasi di controller.
Route::post('/xendit/notification', [XenditWebhookController::class, 'handle'])
    ->name('xendit.notification');
