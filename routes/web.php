<?php

use App\Http\Controllers\PageController;
use App\Http\Controllers\WhatsappPortalController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'index'])->name('home');
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/portfolio', [PageController::class, 'portfolio'])->name('portfolio');
Route::get('/services', [PageController::class, 'services'])->name('services');
Route::get('/stats', [PageController::class, 'stats'])->name('stats');


Route::prefix('whatsapp-portal')->name('whatsapp.')->group(function () {
    Route::get('/', [WhatsappPortalController::class, 'index'])->name('portal');
    Route::post('/login', [WhatsappPortalController::class, 'login'])->name('login');
    Route::get('/status', [WhatsappPortalController::class, 'status'])->name('status');
    Route::get('/qr', [WhatsappPortalController::class, 'qr'])->name('qr');
    Route::post('/pair', [WhatsappPortalController::class, 'pair'])->name('pair');
    Route::post('/reconnect', [WhatsappPortalController::class, 'reconnect'])->name('reconnect');
    Route::delete('/logout', [WhatsappPortalController::class, 'logout'])->name('logout');
    Route::post('/exit', [WhatsappPortalController::class, 'exitPortal'])->name('exit');
});
