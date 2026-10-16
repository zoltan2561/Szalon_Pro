<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PublicController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicController::class, 'home'])->name('home');
Route::get('/adatkezeles', fn () => view('public.privacy'))->name('privacy');
Route::get('/foglalas', [PublicController::class, 'book'])->name('booking.form');
Route::post('/foglalas', [PublicController::class, 'store'])->middleware('throttle:booking')->name('booking.store');
Route::get('/foglalas/kesz', [PublicController::class, 'success'])->name('booking.success');
Route::get('/kezeles/{token}', [PublicController::class, 'manage'])->middleware('throttle:booking')->name('booking.manage');
Route::post('/kezeles/{token}', [PublicController::class, 'update'])->middleware('throttle:booking')->name('booking.update');
Route::get('/belepes', fn () => view('auth.login'))->name('login');
Route::post('/belepes', [AuthController::class, 'login'])->middleware('throttle:login')->name('login.submit');
Route::post('/kilepes', [AuthController::class, 'logout'])->name('logout');
Route::prefix('admin')->middleware('admin')->name('admin.')->group(function (): void {
    Route::get('/', [AdminController::class, 'today'])->name('today');
    Route::get('/naptar', [AdminController::class, 'today'])->name('calendar');
    Route::get('/uj-foglalas', [AdminController::class, 'create'])->name('create');
    Route::post('/foglalas', [AdminController::class, 'store'])->name('store');
    Route::get('/foglalas/{booking}', [AdminController::class, 'booking'])->name('booking');
    Route::post('/foglalas/{booking}', [AdminController::class, 'updateBooking'])->name('booking.update');
    Route::get('/vendegek', [AdminController::class, 'guests'])->name('guests');
    Route::get('/vendegek/{guest}', [AdminController::class, 'guest'])->name('guest');
    Route::post('/vendegek/{guest}/megjegyzes', [AdminController::class, 'notes'])->name('notes');
    Route::post('/vendegek/{guest}/adatok', [AdminController::class, 'contact'])->name('contact');
    Route::middleware('admin:owner')->group(function (): void {
        Route::get('/vendegek/{guest}/export', [AdminController::class, 'export'])->name('export');
        Route::post('/vendegek/{guest}/torles', [AdminController::class, 'anonymize'])->name('anonymize');
        Route::get('/szolgaltatasok', [AdminController::class, 'services'])->name('services');
        Route::post('/szolgaltatasok/{service?}', [AdminController::class, 'saveService'])->name('services.save');
        Route::get('/munkatarsak', [AdminController::class, 'staff'])->name('staff');
        Route::post('/munkatarsak/{staff?}', [AdminController::class, 'saveStaff'])->name('staff.save');
        Route::post('/blokkolas', [AdminController::class, 'block'])->name('block');
        Route::delete('/blokkolas/{block}', [AdminController::class, 'removeBlock'])->name('block.delete');
        Route::post('/napi-munkarend', [AdminController::class, 'exception'])->name('exception');
        Route::delete('/napi-munkarend/{exception}', [AdminController::class, 'removeException'])->name('exception.delete');
        Route::get('/beallitasok', [AdminController::class, 'settings'])->name('settings');
        Route::post('/beallitasok', [AdminController::class, 'saveSettings'])->name('settings.save');
        Route::get('/ertesitesek', [AdminController::class, 'notifications'])->name('notifications');
        Route::post('/ertesitesek/{message}/ujra',[AdminController::class, 'retry'])->name('retry');
    });
});
